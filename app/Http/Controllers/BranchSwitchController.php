<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditLogger;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchSwitchController extends Controller
{
    public function __invoke(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer']]);
        $branch = Branch::whereKey($data['branch_id'])->firstOrFail(); // tenant scope → 404 untuk tenant lain

        abort_unless($request->user()->canAccessBranch($branch), 403, 'Anda tidak memiliki akses ke cabang ini.');

        $request->session()->put('active_branch_id', $branch->id);

        return back()->with('status', "Cabang aktif: {$branch->name}");
    }
}
