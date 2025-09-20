<div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800">
    <div class="container py-5">
        <!-- Header -->
        <div class="text-center mb-4">
            <h1 class="display-5 fw-bold text-primary">Debug Viewer</h1>
            <p class="text-muted lead mx-auto" style="max-width: 700px;">
                Monitor and analyze your debug data in real-time with advanced filtering and visualization
            </p>
        </div>

        <!-- Flash Message -->
        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                {{ session('message') }}
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Filter & Controls -->
        <div class="card mb-4 border">
            <div class="card-header d-flex align-items-center fw-semibold">
                <i class="bi bi-funnel-fill me-2 text-primary"></i> Filter & Control
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Search -->
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="Search keyword or line number">
                        </div>
                    </div>

                    <!-- Filter by Type -->
                    <div class="col-md-3">
                        <label class="form-label">Filter by Type</label>
                        <select wire:model.live="filterByType" class="form-select">
                            <option value="">All Types</option>
                            @foreach($types as $type)
                                <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filter by File -->
                    <div class="col-md-3">
                        <label class="form-label">Filter by File</label>
                        <select wire:model.live="filterByFile" class="form-select">
                            <option value="">All Files</option>
                            @foreach($files as $file)
                                <option value="{{ $file }}">{{ $file }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Actions -->
                    <div class="col-md-3 d-flex flex-column justify-content-end">
                        <div class="d-grid gap-2">
                            <button wire:click="refreshData" class="btn btn-primary">
                                <i class="bi bi-arrow-repeat me-1"></i> Refresh
                            </button>
                            <button wire:click="clearFilters" class="btn btn-secondary">
                                <i class="bi bi-x-circle me-1"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between align-items-center">
                    <div class="form-check">
                        <input type="checkbox" wire:model.live="autoRefresh" class="form-check-input" id="autoRefresh">
                        <label class="form-check-label" for="autoRefresh">Auto Refresh</label>
                    </div>
                    <span class="badge bg-light text-dark">Total Records: {{ count($debugs) }}</span>
                    <button wire:click="clearAllDebugData" onclick="return confirm('Are you sure?')" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash me-1"></i> Clear All Data
                    </button>
                </div>
            </div>
        </div>

        <!-- Debug Table -->
        <div class="card">
            <div class="card-header d-flex align-items-center fw-semibold">
                <i class="bi bi-journal-code me-2 text-primary"></i> Debug Records
            </div>
            <div class="card-body p-0">
                @if(count($debugs) > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>File</th>
                                <th>Line</th>
                                <th>Time</th>
                                <th>Variable Value</th>
                                <th class="text-end">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($debugs as $debug)
                                @php
                                    $preview = is_string($debug['value'])
                                        ? strip_tags($debug['value'])
                                        : json_encode($debug['value'], JSON_UNESCAPED_SLASHES);
                                @endphp

                                <tr wire:click="selectDebug({{ $debug['id'] }})" style="cursor:pointer">
                                    <td>
                                            <span class="badge
                                                @if($debug['debug_type'] === 'json') bg-primary
                                                @elseif($debug['debug_type'] === 'number') bg-success
                                                @else bg-warning text-dark @endif">
                                                {{ ucfirst($debug['debug_type']) }}
                                            </span>
                                    </td>
                                    <td class="text-truncate" style="max-width: 200px;">{{ $debug['class_name'] }}</td>
                                    <td><span class="badge bg-secondary">{{ $debug['line_number'] }}</span></td>
                                    <td><small class="text-muted">{{ $debug['created_at']->diffForHumans() }}</small></td>
                                    <td class="text-truncate" style="max-width: 250px;">
                                        <span class="small text-muted">{{ \Illuminate\Support\Str::limit($preview, 80) }}</span>
                                    </td>
                                    <td class="text-end">
                                        @if(in_array($debug['debug_type'], ['json', 'text']))
                                            <button onclick="copyToClipboard(this, '{{ addslashes($debug['raw_value']) }}'); event.stopPropagation();" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        @endif
                                        <button class="btn btn-sm btn-outline-info">
                                            <i class="bi bi-chevron-down {{ $selectedDebugId === $debug['id'] ? 'rotate-180' : '' }}"></i>
                                        </button>
                                    </td>
                                </tr>

                                @if($selectedDebugId === $debug['id'])
                                    <tr>
                                        <td colspan="6" class="bg-light">
                                            @if($debug['debug_type'] === 'json')
                                                <pre class="bg-white border rounded p-3 mt-2"><code>{{ json_encode($debug['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                            @elseif($debug['debug_type'] === 'text')
                                                <pre class="bg-light border rounded p-3 mt-2"><code>{{ $debug['value'] }}</code></pre>
                                            @elseif($debug['debug_type'] === 'number')
                                                <div class="alert alert-success mb-0">
                                                    <strong>{{ $debug['value'] }}</strong> ({{ is_int($debug['value']) ? 'Integer' : 'Float' }})
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bi bi-clipboard-x fs-1 text-muted"></i>
                        <h5 class="mt-3">No Debug Data Found</h5>
                        <p class="text-muted">No debug records match your current filters, or no data has been captured yet.</p>
                        <button wire:click="refreshData" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-clockwise me-1"></i> Refresh Data
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Auto-refresh script -->
    @if($autoRefresh)
        <script>
            document.addEventListener('livewire:initialized', () => {
                const refreshInterval = setInterval(() => {
                    if (document.querySelector('[wire\\:model\\.live="autoRefresh"]').checked) {
                        Livewire.dispatch('refresh-debug-data');
                    } else {
                        clearInterval(refreshInterval);
                    }
                }, 5000);
            });
        </script>
    @endif

    <!-- Copy to clipboard function -->
    <script>
        function copyToClipboard(button, text) {
            navigator.clipboard.writeText(text).then(() => {
                const original = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
                setTimeout(() => {
                    button.innerHTML = original;
                }, 1500);
            }).catch(err => {
                console.error('Failed to copy: ', err);
                alert('Failed to copy to clipboard');
            });
        }
    </script>
</div>
