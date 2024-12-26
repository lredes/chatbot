<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BancardResponse extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'link_alias',
        'link_url',
        'status',
        'response_code',
        'response_description',
        'amount',
        'currency',
        'installment_number',
        'description',
        'date_time',
        'ticket_number',
        'authorization_code',
        'commerce_name',
        'branch_name',
        'created_at',
        'reference_id',
        'bin',
        'type',
        'payer',
        'entity_id',
        'entity_name',
        'brand_id',
        'brand_name',
        'product_id',
        'product_name',
        'affinity_id',
        'affinity_name',
        'payment_type_description',
        'card_last_numbers',
        'account_type',
        'additional_info',
    ];
}
