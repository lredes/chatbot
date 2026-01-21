<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Models\Cliente;
use App\Models\Paquete;
use App\Models\PagoChat;
use App\Models\ClienteToken;
use Illuminate\Http\Request;
use App\Models\BancardResponse;
// import guzzle 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\AuditoriaIntegracion;
use App\Http\Requests\ObtenerPagoRequest;
use Illuminate\Support\Facades\Validator;


class ApiController extends Controller
{

    public function login(Request $request)
    {

        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);

        $validator = Validator::make($request->all(), [
            'codigo_cliente' => 'integer',
            'celular' => 'string|max:15|regex:/^\+?[0-9]{7,15}$/',
        ]);


        if ($validator->fails()) {
            $respuesta->put('desc_respuesta', 'Parámetros inválidos');
            return response()->json($respuesta, 422);
        }
        $codigo_cliente = $request->get('codigo_cliente') ?? null;
        $celular = $request->get('celular') ?? null;

        try {
            if (!empty($codigo_cliente)) {
                $cliente = Cliente::where('clientecodigo', $codigo_cliente)
                                ->orWhere('clienteci', $codigo_cliente)
                                ->first();
            } else {
                $numeros = $this->formatPhoneNumber($celular);
                info('numeros');
                info($numeros);
                $cliente = Cliente::whereIn('clientecelular', $numeros)
                    ->orWhereIn('clientetelefono', $numeros)
                    ->first();
            }

            if (empty($cliente)) {
                $respuesta->put('desc_respuesta', 'No se encontraron datos');
            } else {
                $respuesta->put('desc_respuesta', 'OK');
                $respuesta->put('exito', true);
                $token = $this->generarToken($cliente->clientecodigo);
                $respuesta->put('token', $token);
                $respuesta->put('nombre_cliente', $cliente->clientenombre);
                $respuesta->put('nombre_apellido', $cliente->clienteapellido);
                $respuesta->put('nombre_ruc', $cliente->ruc);
            }
            $this->generarAuditoria($request, $respuesta, $uniqueId, 'login');
        } catch (\Exception $e) {
            $respuesta->put('desc_respuesta', "Algo salió mal");
            info("Error en login " . $e->getMessage());
        }

        $this->generarAuditoria($request, $respuesta, $uniqueId, 'login');
        return $respuesta;
    }

    public function locker_disponibilidad($sucursal_id)
    {
        try {
            $respuesta = collect([
                'exito' => false,
                'desc_respuesta' => 'No se han recibido los parametros',
            ]);


            $token = $this->getTokenChat();

            $client = new Client([
                'base_uri' => 'http://104.238.131.213:9000'
            ]);

            try {
                $response = $client->get('/api/v1/chatbot/disponibilidad', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                    ],
                    'query' => [
                        'sucursal_id' => $sucursal_id,
                    ]
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode == 200) {
                    $responseBody = json_decode($response->getBody(), true);
                    $msg = $responseBody['msg'];
                    $respuesta->put('exito', true);

                    if ($msg != "Locker disponible") {
                        $respuesta->put('disponible', false);
                        $respuesta->put('desc_respuesta', "En este momento los lockers se encuentran al límite de capacidad. Por favor, intente nuevamente más tarde.");
                    } else {
                        $respuesta->put('disponible', true);
                        $respuesta->put('desc_respuesta', "Locker disponible");
                    }
                } else {
                    $errorResponse = json_decode($response->getBody(), true);
                    $respuesta->put('exito', false);
                    $respuesta->put('desc_respuesta', "Algo salió mal. Por favor, intente nuevamente más tarde.");
                }
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                if ($e->getResponse()->getStatusCode() === 400) {
                    $errorResponse = json_decode($e->getResponse()->getBody(), true);
                    $respuesta->put('exito', false);
                    $respuesta->put('desc_respuesta', $errorResponse['msg']);
                } else {
                    throw $e;
                }
            }

            return $respuesta;
        } catch (Exception $e) {
            $respuesta = collect([
                'exito' => false,
                'desc_respuesta' => 'Ocurrió un error inesperado. Por favor, intente nuevamente más tarde.',
            ]);

            return $respuesta;
        }
    }

    private function checkNumero($numero, $cliente)
    {

        $numeroBD = $cliente->clientecelular;

        if (strpos($numeroBD, '+5') !== false) {
            $numeroBD = preg_replace('/[^0-9]/', '', $numeroBD);
            $numeroBD = '+' . $numeroBD;
            return $numeroBD == $numero;
        } else {
            $numeroBD = preg_replace('/[^0-9]/', '', $numeroBD);

            if (strpos($numeroBD, '0') === 0) {
                $numeroBD = '+595' . substr($numeroBD, 1);
                return $numeroBD == $numero;
            } else {

                if (strpos($numeroBD, '5') === 0) {
                    $numeroBD = '+' . $numeroBD;
                } else {
                    $numeroBD = '+595' . $numeroBD;
                }
                return $numeroBD == $numero;
            }
        }
    }

    private function generarToken($codigo)
    {
        $token = md5(uniqid()) . md5(uniqid());
        $fechaExpiracion = Carbon::now()->addMinutes(100);
        $clienteToken = ClienteToken::where('cliente_id', $codigo)->first();
        if (!empty($clienteToken)) {
            $clienteToken->token = $token;
            $clienteToken->fecha_expiracion = $fechaExpiracion;
            $clienteToken->save();
            return $token;
        }

        $clienteToken = ClienteToken::create([
            'token' => $token,
            'cliente_id' => $codigo,
            'fecha_expiracion' => $fechaExpiracion,
        ]);
        return $token;
    }

    public function refreshToken(Request $request)
    {
        $token = $request->get('token') ?? null;
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);

        if (empty($token)) {
            $respuesta->put('desc_respuesta', 'No se han recibido los parametros');
        } else {
            try {
                $clienteToken = ClienteToken::where('token', $token)->where('fecha_expiracion', '>', Carbon::now())->first();
                if (empty($clienteToken)) {
                    $respuesta->put('desc_respuesta', 'El token no es valido');
                } else {
                    $clienteToken->fecha_expiracion = Carbon::now()->addMinutes(10);
                    $clienteToken->save();

                    $respuesta->put('desc_respuesta', 'OK');
                    $respuesta->put('exito', true);
                    $respuesta->put('token', $clienteToken->token);
                }
            } catch (\Exception $e) {
                $respuesta->put('desc_respuesta', "Algo salio mal");
                info("Error en refreshToken " . $e->getMessage());
            }
        }

        $this->generarAuditoria($request, $respuesta, $uniqueId, 'refresh_token');
        return $respuesta;
    }

    public function paquetesss(Request $request, $pago = false)
    {
        $token = $request->get('token') ?? null;
        $uniqueId = isset($request->id_transaccion) ? $request->id_transaccion : md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        if (empty($token) && !$pago) {
            $respuesta->put('desc_respuesta', 'No se han recibido los parametros');
        } else {
            try {
                if ($pago) {
                    $clienteId = Cliente::where('clienteci', $request->ci_ruc)->orWhere('ruc', $request->ci_ruc)->first()->clientecodigo ?? null;
                } else {
                    $clienteToken = ClienteToken::where('token', $token)->where('fecha_expiracion', '>', Carbon::now())->first();
                    $clienteId =  $clienteToken->cliente_id ?? null;
                }

                if (!isset($clienteId)) {
                    $respuesta->put('desc_respuesta', 'El cliente no se encontro');
                } else if (!isset($clienteToken) && !$pago) {
                    $respuesta->put('desc_respuesta', 'El token no es valido');
                } else {

                    $token = $this->getTokenChat();
                    $client = new Client([
                        'base_uri' => 'https://sistemav2.frontlinerpy.com',                            
                    ]);
                    $cliente = $clienteToken->cliente;
                    $response = $client->post('/api/chatbot/crear', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $token,
                        ],
                        'json' => [
                            'modalidad' => $request->modalidad ?? 'C',
                            'fecha_retiro' => $request->fecha_retiro ?? Carbon::now()->format('Y-m-d'),
                            'sucursal_id' => $cliente->sucursal,
                            'hora_retiro' => $request->hora_retiro ?? 37800,
                            'clientecodigo' => $cliente->clientecodigo,
                            'guardar' => false
                        ]
                    ]);
                    $response = json_decode($response->getBody(), true);
                    return $response;
                }
            } // add guzzle exception
            catch (\GuzzleHttp\Exception\ClientException $e) {
                if ($e->getResponse()->getStatusCode() === 400) {
                    $errorResponse = json_decode($e->getResponse()->getBody(), true);
                    $respuesta->put('exito', true);
                    $respuesta->put('desc_respuesta', $errorResponse['msg']);
                }
            } catch (\Exception $e) {
                $respuesta->put('desc_respuesta', "Algo salio mal");
                info("Error en paquetes " . $e->getMessage());
                info("En la linea " . $e->getLine());
            }
        }
        $this->generarAuditoria($request, $respuesta, $uniqueId, 'paquetes');
        return $respuesta;
    }

    public function paquetesEnTransito(Request $request)
    {
        $token = $request->get('token') ?? null;
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);

        if (empty($token)) {
            $respuesta->put('desc_respuesta', 'No se han recibido los parametros');
        } else {
            try {
                $clienteToken = ClienteToken::where('token', $token)
                    ->where('fecha_expiracion', '>', Carbon::now())
                    ->first();

                if (empty($clienteToken)) {
                    $respuesta->put('desc_respuesta', 'El token no es valido');
                } else {
                    $clienteCodigo = $clienteToken->cliente_id;

                    $paquetesEnTransito = DB::table('paquetes')
                        ->join('embarques', 'paquetes.embarquecodigo', '=', 'embarques.embarquecodigo')
                        ->where('paquetes.estado', 'A')
                        ->where('estadoembarquedescripcion', 'TRANSITO')
                        ->where('paquetes.clientecodigo', $clienteCodigo)
                        ->select('embarques.embarquecodigo', 'embarques.fechallegada', DB::raw('COUNT(paquetes.paquetecodigo) as cantidad'))
                        ->groupBy('embarques.embarquecodigo', 'embarques.fechallegada')
                        ->get();

                    $cantidadPaquetes = $paquetesEnTransito->sum('cantidad');

                    $embarques = $paquetesEnTransito->map(function ($item) {
                        return [
                            'nro_embarque' => $item->embarquecodigo,
                            'paquetes' => [
                                'fecha_llegada' => $item->fechallegada,
                                'cantidad' => $item->cantidad,
                            ]
                        ];
                    });

                    $respuesta->put('desc_respuesta', 'OK');
                    $respuesta->put('exito', true);
                    $respuesta->put('cantidad_paquetes', $cantidadPaquetes);
                    $respuesta->put('embarques', $embarques);
                }
            } catch (\Exception $e) {
                $respuesta->put('desc_respuesta', "Algo salio mal");
                info("Error en paquetesEnTransito " . $e->getMessage());
            }
        }

        $this->generarAuditoria($request, $respuesta, $uniqueId, 'paquetes_en_transito');
        return $respuesta;
    }

    public function redondearMonto($monto)
    {
        if ($monto < 100) {
            return $monto;
        }

        $monto = (int)$monto;
        $last3digits = substr($monto, -2);
        if ($last3digits >= 50) {
            $monto = $monto + (50 - $last3digits);
        } else {
            $monto = $monto - $last3digits;
        }

        return $monto;
    }

    public function paquetes_cantidad(Request $request)
    {
        $token = $request->get('token') ?? null;
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        if (empty($token)) {
            $respuesta->put('desc_respuesta', 'No se han recibido los parametros');
        } else {
            try {

                $clienteToken = ClienteToken::where('token', $token)->where('fecha_expiracion', '>', Carbon::now())->first();

                if (empty($clienteToken)) {
                    $respuesta->put('desc_respuesta', 'El token no es valido');
                    return $respuesta;
                } else {
                    $paquetes = Paquete::where('paquetes.estado', 'B')
                        ->where('clientes.clientecodigo', $clienteToken->cliente_id)
                        ->where('estadoembarquedescripcion', 'ASUNCION')
                        ->leftjoin('clientes', 'clientes.clientecodigo', '=', 'paquetes.clientecodigo')
                        ->leftjoin('embarques', 'embarques.embarquecodigo', '=', 'paquetes.embarquecodigo')
                        ->count();


                    $respuesta->put('desc_respuesta', 'OK');
                    $respuesta->put('paquetes', $paquetes);
                    $respuesta->put('exito', true);
                }
            } catch (\Exception $e) {
                $respuesta->put('desc_respuesta', "Algo salio mal");
                info("Error en paquetes_cantidad " . $e->getMessage());
            }
        }
        $this->generarAuditoria($request, $respuesta, $uniqueId, 'paquetes_cantidad');
        return $respuesta;
    }

    public function cliente(Request $request)
    {
        $token = $request->get('token') ?? null;
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        if (empty($token)) {
            $respuesta->put('desc_respuesta', 'No se han recibido los parametros');
        } else {
            try {

                $clienteToken = ClienteToken::where('token', $token)->where('fecha_expiracion', '>', Carbon::now())->first();
                if (empty($clienteToken)) {
                    $respuesta->put('desc_respuesta', 'El token no es valido');
                    return $respuesta;
                } else {
                    $cliente = Cliente::where('clientecodigo', $clienteToken->cliente_id)
                        ->leftjoin('sucursal', 'sucursal.sucursal', '=', 'clientes.sucursal')
                        ->select(
                            DB::raw("CONCAT(clientenombre, ' ', clienteapellido ) as nombre"),
                            'clientecodigo as codigo',
                            'sucursal.nombre as sucursal'
                        )
                        ->first();

                    if (empty($cliente)) {
                        $respuesta->put('desc_respuesta', 'No se ha encontrado el cliente');
                    } else {
                        $arrayDirecciones = [
                            'CARMELITAS' => 'https://goo.gl/maps/WtAaqEeVdXGKMCrNA',
                            'CENTRAL' => 'https://goo.gl/maps/diAv4Sitv1FSbhfp9',
                            'AG ESPANA' => 'https://goo.gl/maps/1H1FD4GbXQQJBJoQ6',
                            'FDO. DE LA MORA SUR' => 'https://goo.gl/maps/ibaUkQXCeQzUZ3UGA',
                        ];
                        $respuesta->put('desc_respuesta', 'OK');
                        $cliente->link_maps = $arrayDirecciones[$cliente->sucursal] ?? null;
                        $respuesta->put('cliente', $cliente);
                        $respuesta->put('datos_casilla', [
                            'direccion_aerea' => [
                                'nombre' => $cliente->nombre,
                                'direccion_principal' => '13230 SW 132ND AVE STE 25',
                                'referencia' => 'FRONTLINER USA',
                                'ciudad_estado' => 'MIAMI, FLORIDA',
                                'codigo_postal' => '33186-0014',
                                'nro_tel' => '+1 305 647 1927'
                            ],
                            'direccion_maritima' => [
                                'nombre' => $cliente->nombre,
                                'direccion_principal' => '11000 NW 36TH AVE.',
                                'referencia' => 'FRONTLINER USA',
                                'ciudad_estado' => 'MIAMI, FLORIDA',
                                'codigo_postal' => '33167',
                                'nro_tel' => '+1 305 628 4227'
                            ],
                        ]);
                    }
                    $respuesta->put('exito', true);
                    if ($cliente->sucursal == 'CENTRAL' || $cliente->sucursal == 'CARMELITAS') {
                        $respuesta->put('locker', true);
                    } else {
                        $respuesta->put('locker', false);
                    }
                    $this->prolongarToken($clienteToken->token);
                }
            } catch (\Exception $e) {
                $respuesta->put('desc_respuesta', "Algo salio mal");
                info("Error en cliente " . $e->getMessage());
            }
        }
        $this->generarAuditoria($request, $respuesta, $uniqueId, 'cliente');
        return $respuesta;
    }

    private function getTokenChat()
    {
        $client = new Client([
			'base_uri' => 'https://sistemav2.frontlinerpy.com',                            
        ]);
        $response = $client->post('/api/token/', [
            'json' => [
                'username' => 'chatbot',
                'password' => '#chat!2025Front'
            ]
        ]);

        $content = $response->getBody()->getContents();
        info($content);

        $token_data = json_decode($content);

        if (isset($token_data->access)) {
            $token = $token_data->access;
            return $token; // Devuelve el token directamente
        } else {
            return null;
        }
    }

    private function generarAuditoria($request, $respuesta, $uniqueId, $funcion)
    {

        AuditoriaIntegracion::create([
            'uniqueid' => $uniqueId,
            'funcion' => $funcion,
            'ips' => $request->ip(),
            'metodo' => $request->method(),
            'request' => json_encode($request->all()),
            'response' => json_encode($respuesta),
            'user_agent' => $request->header('User-Agent') ?? null,
        ]);
    }

    private function formatPhoneNumber($phone)
    {
        $numberOnly = preg_replace('/\D/', '', $phone);

        $formattedNumbers = [];

        $localNumber = substr($numberOnly, 3);
        $areaCode = substr($localNumber, 0, 3);
        $restOfNumber = substr($localNumber, 3);
        $firstThreeDigits = substr($restOfNumber, 0, 3);
        $lastThreeDigits = substr($restOfNumber, 3);

        $formattedNumbers[] = $numberOnly;
        $formattedNumbers[] = $localNumber;
        $formattedNumbers[] = "0$localNumber";
        $formattedNumbers[] = "+$numberOnly";
        $formattedNumbers[] = "($localNumber)";
        $formattedNumbers[] = "(0$areaCode)$restOfNumber";
        $formattedNumbers[] = "(0$areaCode) $restOfNumber";
        $formattedNumbers[] = "( 0$areaCode ) $restOfNumber";
        $formattedNumbers[] = "( 0$areaCode )$restOfNumber";
        $formattedNumbers[] = "(0$areaCode)$firstThreeDigits-$lastThreeDigits";
        $formattedNumbers[] = "0$areaCode-$restOfNumber";
        $formattedNumbers[] = "0$areaCode-$firstThreeDigits $lastThreeDigits";
        $formattedNumbers[] = "0$areaCode-$firstThreeDigits-$lastThreeDigits";
        $formattedNumbers[] = "$areaCode-$firstThreeDigits-$lastThreeDigits";
        $formattedNumbers[] = "($areaCode)$firstThreeDigits-$lastThreeDigits";

        return $formattedNumbers;
    }

    public function normalize_ci_ruc($ci_ruc)
    {
        // Verifica si la entrada contiene un guion, lo que indica que es un RUC
        if (strpos($ci_ruc, '-') !== false) {
            $parts = preg_split('/[-.]/', $ci_ruc);
            $main = number_format(preg_replace('/\D/', '', $parts[0]), 0, '', '.');
            $digit = $parts[1];
            $ci_ruc_normalized = $main . '-' . $digit;
        }
        // Si no contiene un guion, se asume que es un CI
        else {
            $ci_ruc_normalized = number_format(preg_replace('/\D/', '', $ci_ruc), 0, '', '.');
        }

        return $ci_ruc_normalized;
    }

    public function sucursales(Request $request)
    {

        $uniqueId = isset($request->id_transaccion) ? $request->id_transaccion : md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        try {
            $clienteToken = ClienteToken::where('token', $request->token)->where('fecha_expiracion', '>', Carbon::now())->first();
            $clienteId =  $clienteToken->cliente_id ?? null;


            if (empty($clienteToken)) {
                $respuesta->put('desc_respuesta', 'El token no es valido');
                return $respuesta;
            }
            $sucursal_actual = Cliente::where('clientecodigo', $clienteId)->first()->sucursal_actual ?? null;
            $respuesta->put('sucursal_actual', $sucursal_actual);
            $client = new Client([
                'base_uri' => 'https://sistemav2.frontlinerpy.com',                            
            ]);
            $token = $this->getTokenChat();
            $response = $client->get('/api/chatbot/obtener_sucursales', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);
            $sucursales = json_decode($response->getBody(), true);
            if ($sucursal_actual) {
                $sucursales = collect($sucursales)->filter(function ($sucursal) use ($sucursal_actual) {
                    return $sucursal['sucursal'] != $sucursal_actual->sucursal;
                })->values();
            }

            $respuesta->put('desc_respuesta', 'OK');
            $respuesta->put('exito', true);
            $respuesta->put('sucursales', $sucursales);
        } catch (\Exception $e) {
            $respuesta->put('desc_respuesta', "Algo salio mal");
            info("Error en sucursales " . $e->getMessage());
        }
        $this->generarAuditoria($request, $respuesta, $uniqueId, 'sucursales');
        return $respuesta;
    }

    public function direccion_miami(Request $request)
    {
        $uniqueId = isset($request->id_transaccion) ? $request->id_transaccion : md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        try {
            $tipo = $request->tipo === 'MIAMI WH/AEREO' ? 'MIAMI WH/AEREO' : 'MARITIMO - OCEAN';
            $clienteToken = ClienteToken::where('token', $request->token)->where('fecha_expiracion', '>', Carbon::now())->first();
            if (empty($clienteToken)) {
                $respuesta->put('desc_respuesta', 'El token no es valido');
                return $respuesta;
            }
            $cliente = $clienteToken->cliente;
            $direccion_cliente = $cliente->clientenombre . ' ' . $cliente->clienteapellido . ' #' . $cliente->clientecodigo;
            $direcciones = DB::table('ofremota')->get();
            // traer las dos direcciones :
            $direcciones->each(function ($direccion) use ($direccion_cliente) {
                $direccion->direccion = $direccion_cliente . "\r\n" . $direccion->direccion;
            });
            $respuesta->put('desc_respuesta', 'OK');
            $respuesta->put('exito', true);
            $respuesta->put('direcciones', $direcciones);
        } catch (\Exception $e) {
            $respuesta->put('desc_respuesta', "Algo salio mal");
            info("Error en direccion_miami " . $e->getMessage());
        }

        return $respuesta;
    }

    private function prolongarToken($token)
    {
        $clienteToken = ClienteToken::where('token', $token)->where('fecha_expiracion', '>', Carbon::now())->first();
        if (!empty($clienteToken)) {
            $clienteToken->fecha_expiracion = Carbon::now()->addMinutes(10);
            $clienteToken->save();
        }
    }

    public function horario_retiro(Request $request)
    {
        $uniqueId = isset($request->id_transaccion) ? $request->id_transaccion : md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        $validator = Validator::make($request->all(), [
            'modalidad' => 'required|in:C,D',
            'fecha_retiro' => 'nullable|date',
            'token' => 'required',
        ]);

        if ($validator->fails()) {
            $respuesta->put('desc_respuesta', 'Faltan campos requeridos: ' . implode(', ', $validator->errors()->all()));
            return response()->json($respuesta, 422);
        }
        try {
            $clienteToken = ClienteToken::where('token', $request->token)->where('fecha_expiracion', '>', Carbon::now())->first();
            $clienteId =  $clienteToken->cliente_id ?? null;
            $modalidad = $request->modalidad;

            if (empty($clienteToken)) {
                $respuesta->put('desc_respuesta', 'El token no es valido');
                return $respuesta;
            }
            $client = new Client([
				'base_uri' => 'https://sistemav2.frontlinerpy.com',                            
            ]);
            $token = $this->getTokenChat();
            $response = $client->get('/api/chatbot/obtener_horarios', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'query' => [
                    'modalidad' => $modalidad,
                    'fecha_retiro' => $request->fecha_retiro ?? Carbon::now()->format('Y-m-d'),
                    'sucursal_id'  => $clienteToken->cliente->sucursal,
                ]
            ]);
            $horarios = json_decode($response->getBody(), true);


            $respuesta->put('desc_respuesta', 'OK');
            $respuesta->put('exito', true);
            $respuesta->put('horarios', $horarios);
        } catch (\Exception $e) {
            $respuesta->put('desc_respuesta', "Algo salio mal");
            info("Error en horario_retiro " . $e->getMessage());
        }
        $this->generarAuditoria($request, $respuesta, $uniqueId, 'horario_retiro');
        $this->prolongarToken($request->token);
        return $respuesta;
    }

    public function obtenerDirecciones(Request $request)
    {
        $uniqueId = md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);

        $validator = Validator::make($request->all(), [
            'clientecodigo' => 'nullable',
            'token' => 'required',
        ]);

        if ($validator->fails()) {
            $respuesta->put('desc_respuesta', 'Faltan campos requeridos: ' . implode(', ', $validator->errors()->all()));
            return response()->json($respuesta, 422);
        }

        try {
            $clienteToken = ClienteToken::where('token', $request->token)
                ->where('fecha_expiracion', '>', Carbon::now())
                ->first();

            if (empty($clienteToken)) {
                $respuesta->put('desc_respuesta', 'El token no es válido o el clientecodigo no coincide');
                return $respuesta;
            }

            $client = new Client([
				'base_uri' => 'https://sistemav2.frontlinerpy.com',                            
            ]);

            $token = $this->getTokenChat();
            $response = $client->get('/api/chatbot/obtener_direcciones', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'query' => [
                    'clientecodigo' => $clienteToken->cliente->clientecodigo,
                ]
            ]);

            $direcciones = json_decode($response->getBody(), true);

            if ($response->getStatusCode() == 200) {
                $respuesta->put('desc_respuesta', 'OK');
                $respuesta->put('exito', true);
                $respuesta->put('direcciones', $direcciones);
            } else {
                $respuesta->put('desc_respuesta', 'Error al obtener direcciones del servicio externo');
            }
        } catch (\Exception $e) {
            $respuesta->put('desc_respuesta', "Algo salió mal");
            info("Error en obtenerDirecciones " . $e->getMessage());
        }

        $this->generarAuditoria($request, $respuesta, $uniqueId, 'obtener_direcciones');
        $this->prolongarToken($request->token);
        return $respuesta;
    }

    public function crear_pedido(Request $request)
    {
        $uniqueId = isset($request->id_transaccion) ? $request->id_transaccion : md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);

        $validator = Validator::make($request->all(), [
            'modalidad' => 'required|in:C,D,L',
            'fecha_retiro' => 'required_if:modalidad,C,D|date',
            'hora_retiro' => 'required_if:modalidad,C,D|integer',
            'token' => 'required',
        ]);

        if ($validator->fails()) {
            $respuesta->put('desc_respuesta', 'Faltan campos requeridos: ' . implode(', ', $validator->errors()->all()));
            return response()->json($respuesta, 422);
        }


        try {
            $clienteToken = ClienteToken::where('token', $request->token)->where('fecha_expiracion', '>', Carbon::now())->first();
            $cliente =  $clienteToken->cliente ?? null;
            if (!in_array($request->modalidad, ['L', 'C', 'D'])) {
                $respuesta->put('desc_respuesta', 'La modalidad no es valida');
                return $respuesta;
            }

            if (empty($clienteToken)) {
                $respuesta->put('desc_respuesta', 'El token no es valido');
                return $respuesta;
            }
            $client = new Client([
                'base_uri' => 'https://sistemav2.frontlinerpy.com',                            
            ]);

            if ($request->modalidad == 'L') {
                $disp = $this->consultar_disponibilidad_p($request->modalidad, $cliente->sucursal, $request->fecha_retiro, $request->hora_retiro);
                if ($disp != "Disponible") {
                    $respuesta->put('desc_respuesta', $disp);
                    $respuesta->put('exito', true);
                    return $respuesta;
                }
            }

            $token = $this->getTokenChat();


            $response = $client->post('/api/chatbot/crear', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'json' => [
                    'modalidad' => $request->modalidad,
                    'fecha_retiro' => $request->fecha_retiro ?? Carbon::now()->format('Y-m-d'),
                    'sucursal_id' => $cliente->sucursal,
                    'hora_retiro' => $request->hora_retiro,
                    'clientecodigo' => $cliente->clientecodigo,
					'direccion_id' => $request->direccion_id ?? null,
                    'guardar' => true
                ]
            ]);
            $response = json_decode($response->getBody(), true);
            info('esto devuelve crear pedido');
            info($response);
            $respuesta->put('desc_respuesta', 'OK');
            $respuesta->put('exito', true);
            $respuesta->put('data', $response);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 400) {
                $errorResponse = json_decode($e->getResponse()->getBody(), true);
                $respuesta->put('desc_respuesta', $errorResponse['msg']);
                $respuesta->put('exito', true);
            } else {
                $respuesta->put('desc_respuesta', "Algo salio mal");
                info("Error en crear_pedido " . $e->getMessage());
            }
        } catch (\Exception $e) {
            $respuesta->put('desc_respuesta', "Algo salio mal");
            info("Error en horario_retiro " . $e->getMessage());
        }
        $this->generarAuditoria($request, $respuesta, $uniqueId, 'horario_retiro');
        return $respuesta;
    }

    public function paquetes(Request $request, $pago = false) {
        $token = $request->get('token') ?? null;
        $uniqueId = isset($request->id_transaccion) ? $request->id_transaccion : md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);
        if (empty($token) && !$pago) {
            $respuesta->put('desc_respuesta', 'No se han recibido los parametros');
        } else {
            try {
                if ($pago) {
                    $clienteId = Cliente::where('clienteci', $request->ci_ruc)->orWhere('ruc', $request->ci_ruc)->first()->clientecodigo ?? null;
                } else {
                    $clienteToken = ClienteToken::where('token', $token)->where('fecha_expiracion', '>', Carbon::now())->first();
                    $clienteId =  $clienteToken->cliente_id ?? null;
                }

                if (!isset($clienteId)) {
                    $respuesta->put('desc_respuesta', 'El cliente no se encontro');
                } else if (!isset($clienteToken) && !$pago) {
                    $respuesta->put('desc_respuesta', 'El token no es valido');
                } else {

                    $token = $this->getTokenChat();
                    $client = new Client([
	                	'base_uri' => 'https://sistemav2.frontlinerpy.com',                            
                    ]);
                    $cliente = $clienteToken->cliente;
                    $response = $client->post('/api/chatbot/crear', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $token,
                        ],
                        'json' => [
                            'modalidad' => $request->modalidad ?? 'L',
                            'fecha_retiro' => $request->fecha_retiro ?? Carbon::now()->format('Y-m-d'),
                            'sucursal_id' => $cliente->sucursal,
                            'hora_retiro' => $request->hora_retiro ?? 37800,
                            'clientecodigo' => $cliente->clientecodigo,
                            'guardar' => false
                        ]
                    ]);
                    $response = json_decode($response->getBody(), true);
                    return $response;
                }
            } // add guzzle exception
            catch (\GuzzleHttp\Exception\ClientException $e) {
                if ($e->getResponse()->getStatusCode() === 400) {
                    $errorResponse = json_decode($e->getResponse()->getBody(), true);
                    $respuesta->put('exito', true);
                    $respuesta->put('desc_respuesta', $errorResponse['msg']);
                }
            } catch (\Exception $e) {
                $respuesta->put('desc_respuesta', "Algo salio mal");
                info("Error en paquetes " . $e->getMessage());
                info("En la linea " . $e->getLine());
            }
        }
        $this->generarAuditoria($request, $respuesta, $uniqueId, 'paquetes');
        return $respuesta;
    }

    private function calcularPeso($paquetes)
    {
        $paquetes = $paquetes->groupBy('embarque')->map(function ($group) {
            return $group;
        });
        $total = 0;
        foreach ($paquetes as $paquete) {
            $peso = 0;
            foreach ($paquete as $p) {
                $peso += $p->peso;
                info(' PESO ES ');
                info($peso);
            }
            if ($peso < 0.1) {
                $peso = 100;
            }
            $paquete->peso = $peso;
            $total += $peso;
        }
        return $total;
    }

    public function consultar_disponibilidad(Request $request)
    {
        $uniqueId = isset($request->id_transaccion) ? $request->id_transaccion : md5(uniqid());
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $uniqueId,
        ]);

        $validator = Validator::make($request->all(), [
            'modalidad' => 'required|in:C,D,L',
            'fecha_retiro' => 'required_if:modalidad,C,D',
            'hora_retiro' => 'sometimes',
            'token' => 'required',
        ]);

        if ($validator->fails()) {
            $respuesta->put('desc_respuesta', 'Faltan campos requeridos: ' . implode(', ', $validator->errors()->all()));
            return response()->json($respuesta, 422);
        }

        $clienteToken = ClienteToken::where('token', $request->token)->where('fecha_expiracion', '>', Carbon::now())->first();
        $cliente =  $clienteToken->cliente ?? null;

        if (empty($clienteToken)) {
            $respuesta->put('desc_respuesta', 'El token no es valido');
            return $respuesta;
        }
        $data = $this->consultar_disponibilidad_p($request->modalidad, $cliente->sucursal);

        return response()->json([
            'exito' => $data != "Error al consultar la disponibilidad",
            'desc_respuesta' => 'OK',
            'data' => $data,
        ]);
    }

    private function consultar_disponibilidad_p($modalidad, $sucursal_id, $fecha_retiro = null,  $hora_retiro = null)
    {

        try {

            $client = new Client([
                'base_uri' => 'https://sistemav2.frontlinerpy.com',                            
            ]);
            $token = $this->getTokenChat();
            $json =  [
                'modalidad' => $modalidad,
                'sucursal_id' => $sucursal_id,
                "fecha_retiro" => $fecha_retiro,
                "hora_retiro" => $hora_retiro,
            ];
            $response = $client->post('api/chatbot/disponibilidad', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $json
            ]);

            $response = json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            $errorResponse = json_decode($responseBody, true);
            $msg = $errorResponse['msg'] ?? "Error desconocido";
            info("Error en consultar_disponibilidad_p: " . $msg);
            return $msg;
        } catch (\Exception $e) {
            info("Error en consultar_disponibilidad_p " . $e->getMessage());
            return "Error al consultar la disponibilidad";
        }
        return $response['msg'] ?? null;
    }

    public function obtener_pago(ObtenerPagoRequest $request)
    {
        $clienteToken = ClienteToken::where('token', $request->token)->where('fecha_expiracion', '>', Carbon::now())->first();
        $respuesta = collect([
            'exito' => false,
            'desc_respuesta' => NULL,
            'id_transaccion' => $request->id_transaccion ?? md5(uniqid()),
        ]);
        if (empty($clienteToken)) {
            return response()->json([
                'exito' => false,
                'desc_respuesta' => 'El token no es valido',
            ]);
        }

        $cliente = $clienteToken->cliente;

        BancardResponse::create($request->all());

        if ($request->payment['status'] == 'confirmed') {
            $token = $this->getTokenChat();
            $client = new Client([
                'base_uri' => 'https://sistemav2.frontlinerpy.com',                            
            ]);
            try {
                $response = $client->request('PATCH', 'api/chatbot/estado', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                    ],
                    'json' => [
                        'estado' => 'pagado',
                        'order_id' => $request->order_id,
                        'suite' => $cliente->clientecodigo,
                    ]
                ]);
                $respuesta->put('desc_respuesta', 'OK');
                $respuesta->put('exito', true);
                $respuesta->put('data', json_decode($response->getBody(), true));
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                if ($e->getResponse()->getStatusCode() === 400) {
                    $errorResponse = json_decode($e->getResponse()->getBody(), true);
                    $respuesta->put('exito', true);
                    $respuesta->put('desc_respuesta', $errorResponse['msg']);
                }
            } catch (\Exception $e) {
                info("Error en obtener_pago " . $e->getMessage());
                $respuesta->put('desc_respuesta', 'Error al actualizar el estado del pedido');
            }


            return response()->json($respuesta);
        }
        $respuesta->put('desc_respuesta', 'Como no se confirmo el pago, no se actualizo el estado del pedido');


        return response()->json($respuesta);
    }
}