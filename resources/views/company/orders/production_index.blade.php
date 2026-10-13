@extends('company_layout.admin')
@section('content')
<div class="content-wrapper">@include('company.orders.nav')<div class="card"><div class="card-body"><h3>{{ $kind === 'cad' ? 'CAD Designs' : 'RPT Production Batches' }}</h3>
<p>{{ $kind === 'cad' ? 'Open a confirmed customer order to assign a CAD design.' : 'Open an approved CAD revision to plan an RPT batch.' }}</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Number</th><th>Order / Customer</th><th>Item</th><th>Status</th><th>Assigned to</th></tr></thead><tbody>
@forelse($records as $record)
@php($line = $kind === 'cad' ? $record->line : $record->design->line)
<tr><td><a href="{{ route('company.'.$kind.'.show', [$company->slug, $record->id]) }}">{{ $record->number }}</a></td><td>{{ $line->order->number }} / {{ $line->order->customer->name }}</td><td>{{ $line->item_name }}</td><td>{{ ucwords(str_replace('_',' ', $record->status)) }}</td><td>{{ $kind === 'cad' ? $record->designer?->name : $record->operator?->name }}</td></tr>
@empty<tr><td colspan="5">No records yet.</td></tr>@endforelse
</tbody></table></div>{{ $records->links() }}</div></div></div>
@endsection
