@if ($paginator->hasPages())
    <nav class="row" aria-label="Navigasi halaman">
        <span class="muted small">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>
        <span class="right row">
            <button class="btn btn-ghost btn-sm" type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" @disabled($paginator->onFirstPage())>Sebelumnya</button>
            <button class="btn btn-ghost btn-sm" type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" @disabled(! $paginator->hasMorePages())>Berikutnya</button>
        </span>
    </nav>
@endif
