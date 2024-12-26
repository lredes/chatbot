<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ObtenerPagoRequest extends FormRequest{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */ 
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'payment.link_alias' => 'nullable',
            'payment.link_url' => 'nullable',
            'payment.status' => 'nullable',
            'payment.response_code' => 'nullable',
            'payment.response_description' => 'nullable',
            'payment.amount' => 'nullable',
            'payment.currency' => 'nullable',
            'payment.installment_number' => 'nullable',
            'payment.description' => 'nullable',
            'payment.datetime' => 'nullable',
            'payment.ticket_number' => 'nullable',
            'payment.authorization_code' => 'nullable',
            'payment.commerce_name' => 'nullable',
            'payment.branch_name' => 'nullable',
            'payment.created_at' => 'nullable',
            'payment.reference_id' => 'nullable',
            'payment.bin' => 'nullable',
            'payment.type' => 'nullable',
            'payment.payer.name' => 'nullable',
            'payment.payer.lastname' => 'nullable',
            'payment.payer.cellphone' => 'nullable',
            'payment.payer.ruc' => 'nullable',
            'payment.payer.email' => 'nullable',
            'payment.payer.notes' => 'nullable',
            'payment.entity_id' => 'nullable',
            'payment.entity_name' => 'nullable',
            'payment.brand_id' => 'nullable',
            'payment.brand_name' => 'nullable',
            'payment.product_id' => 'nullable',
            'payment.product_name' => 'nullable',
            'payment.affinity_id' => 'nullable',
            'payment.affinity_name' => 'nullable',
            'payment.payment_type_description' => 'nullable',
            'payment.card_last_numbers' => 'nullable',
            'payment.account_type' => 'nullable',
        ];
    }

    public function messages()
    {
        $genericMessage = 'Los datos proporcionados no son válidos.';
        return [
            '*' => $genericMessage,
        ];
    }
}
