<?php

namespace App\Domain\Inventory;

use RuntimeException;

/** Kesalahan aturan bisnis stok; pesannya aman ditampilkan ke pengguna. */
class InventoryException extends RuntimeException {}
