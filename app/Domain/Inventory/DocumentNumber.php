<?php

namespace App\Domain\Inventory;

use App\Domain\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/** Nomor dokumen aman terhadap transaksi bersamaan, mis. PO-PST-202610-0007. */
class DocumentNumber
{
    public function next(string $prefix, ?Branch $branch = null): string
    {
        $key = $prefix.'-'.($branch?->code ?? 'X').'-'.now()->format('Ym');

        return DB::transaction(function () use ($key) {
            $tenantId = app(TenantContext::class)->id();
            $seq = DocumentSequence::where('key', $key)->lockForUpdate()->first();
            if (! $seq) {
                $seq = DocumentSequence::create(['tenant_id' => $tenantId, 'key' => $key, 'last_number' => 0]);
                $seq = DocumentSequence::whereKey($seq->id)->lockForUpdate()->first();
            }
            $seq->increment('last_number');

            return $key.'-'.str_pad((string) $seq->last_number, 4, '0', STR_PAD_LEFT);
        });
    }
}
