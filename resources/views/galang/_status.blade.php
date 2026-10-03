<span class="badge {{ ['paid' => 'badge-green', 'pending' => 'badge-amber'][$p->status] ?? 'badge-slate' }}">{{ $p->isPending() && $p->isKedaluwarsa() ? 'Kedaluwarsa' : $p->statusLabel() }}</span>
