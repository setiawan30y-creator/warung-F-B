<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['goods_receipt_id', 'purchase_order_item_id', 'inventory_item_id', 'base_quantity', 'unit_cost', 'total_cost'];
}
