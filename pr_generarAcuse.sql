DROP PROCEDURE IF EXISTS pr_generarAcuse;
DELIMITER //

CREATE PROCEDURE pr_generarAcuse(
    IN p_clientecodigo INT,  OUT p_acuse_id INT
)
BEGIN
    DECLARE v_total_peso DECIMAL(10,2);
    DECLARE v_tarifa DECIMAL(10,2);
    DECLARE v_dolar_venta DECIMAL(10,2);
    DECLARE v_importeGS DECIMAL(10,2);
    DECLARE v_importeUS DECIMAL(10,2);
    DECLARE v_id_factura INT;
    DECLARE v_ruc VARCHAR(20);
    DECLARE v_fecha DATETIME;
    DECLARE v_paquetecodigo INT;
    DECLARE v_paquetepeso DECIMAL(10,2);
    DECLARE v_descripcion VARCHAR(255);
    DECLARE v_montousddet DECIMAL(10,2);
    DECLARE v_montogsdet DECIMAL(10,2);
    DECLARE v_tarifacli DECIMAL(10,2);
    DECLARE v_anualidad DECIMAL(10,2);
    DECLARE v_paquete_id_factura INT;	
    DECLARE v_otroconcepto VARCHAR(255);
    DECLARE v_paquetetracking VARCHAR(255);
    DECLARE v_descripcionotro VARCHAR(255);
    DECLARE fin INTEGER DEFAULT 0;
DECLARE runners_cursor CURSOR FOR 
        SELECT paquetes.paquetecodigo, paquetes.paquetepeso, paquetes.paquetedescripcion, paquetes.tarifapreciocli, paquetes.paquetedescripcion2 ,  paquetes.paquetetracking
        FROM paquetes
        WHERE paquetes.estado = 'B'
          AND paquetes.clientecodigo = p_clientecodigo;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET fin=1;
    

		SELECT MAX(acuse_id) into v_id_factura from facturas;
		SET p_acuse_id = v_id_factura+1;
    SELECT cotizacion.dolar_venta INTO v_dolar_venta   FROM cotizacion  ORDER BY cotizacion.fecha DESC,cotizacion.hora DESC    LIMIT 1;   
    SELECT clientes.tarifa INTO v_tarifa  FROM clientes  WHERE clientes.clientecodigo = p_clientecodigo;   
    SELECT IFNULL(clientes.ruc, clientes.clienteci) INTO v_ruc   FROM clientes   WHERE clientes.clientecodigo = p_clientecodigo;    
    SELECT SUM(paquetes.paquetepeso) INTO v_total_peso FROM paquetes  WHERE paquetes.estado = 'B' AND paquetes.clientecodigo = p_clientecodigo;
    
    SET v_importeGS = (v_total_peso * v_tarifa)*v_dolar_venta;
    SET v_importeUS = v_importeGS / v_dolar_venta;
    
    INSERT INTO facturas (acuse_id, clientecodigo, tarifa, dolar_venta, fecha, ruc, estado, montousd, montogs, obs, peso_real, arqueo, facturaemitida, funcionariocodigo)
    VALUES (p_acuse_id, p_clientecodigo, v_tarifa, v_dolar_venta, NOW(), v_ruc, 1, v_importeUS, v_importeGS, 'Servicio de traslado de paquetes', v_total_peso, 2, 2, 1);
    
    SET v_id_factura = LAST_INSERT_ID();
   
    
    
    OPEN runners_cursor;
  get_runners: LOOP
  FETCH runners_cursor INTO v_paquetecodigo, v_paquetepeso, v_descripcion,   v_tarifacli,   v_otroconcepto, v_paquetetracking;
      
		IF fin = 1 THEN
					LEAVE get_runners;
				END IF;

       
            INSERT INTO facturasdet (acuse_id, acuse_detalle_id, peso, descripcion,  tarifacli,  anualidad, id_factura, otroconcepto, paquetetracking, paquetecodigo)
            VALUES (p_acuse_id, v_paquetecodigo, v_paquetepeso, v_descripcion, v_tarifacli, 2,  p_acuse_id, 2,v_paquetetracking, v_paquetecodigo);

 END LOOP get_runners;

CLOSE runners_cursor;

END//

DELIMITER ;