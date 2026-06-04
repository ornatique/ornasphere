@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">App Theme List</h4>
            <a href="{{ route('company.app-themes.create', $company->slug) }}" class="btn btn-primary">
                Add Theme
            </a>
        </div>

        <div class="card-body">
            <div class="alert alert-info">
                Only one theme is active at a time. The mobile app should use the active theme from the API.
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Primary</th>
                            <th>Secondary</th>
                            <th>Background</th>
                            <th>Text</th>
                            <th width="240">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($themes as $theme)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $theme->name }}</td>
                                <td>{{ ucfirst($theme->mode) }}</td>
                                <td>
                                    @if($theme->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                @foreach(['primary_color', 'secondary_color', 'background_color', 'text_color'] as $field)
                                    <td>
                                        <span class="d-inline-block rounded border me-2 align-middle" style="width:28px;height:18px;background:{{ $theme->{$field} }}"></span>
                                        {{ $theme->{$field} }}
                                    </td>
                                @endforeach
                                <td>
                                    <a href="{{ route('company.app-themes.edit', [$company->slug, $theme->id]) }}" class="btn btn-sm btn-primary">Edit</a>

                                    @if(!$theme->is_active)
                                        <form action="{{ route('company.app-themes.activate', [$company->slug, $theme->id]) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">Activate</button>
                                        </form>
                                    @endif

                                    <form action="{{ route('company.app-themes.destroy', [$company->slug, $theme->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this theme?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center">No app theme created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
