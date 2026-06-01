@forelse($rows as $i => $row)
    <tr>
        <td>{{ $i + 1 }}</td>
        @php
            $dateTimeOrder = optional($row->created_at)->format('YmdHis') ?? optional($row->entry_date)->format('Ymd') . '000000';
            $dateTimeView = optional($row->entry_date)->format('d-m-Y');
            if (!empty($row->created_at)) {
                $dateTimeView .= ' ' . optional($row->created_at)->format('h:i A');
            }
        @endphp
        <td data-order="{{ $dateTimeOrder }}">{{ $dateTimeView }}</td>
        <td>{{ optional($row->customer)->name ?? '-' }}</td>
        <td>
            @if($row->entry_type === 'convert_to_metal')
                Rupees Convert To Metal
            @elseif($row->entry_type === 'convert_to_rupees')
                Metal Convert To Rupees
            @else
                {{ ucwords(str_replace('_', ' ', $row->entry_type)) }}
            @endif
        </td>
        <td>{{ $row->payment_mode ? ucfirst($row->payment_mode) : '-' }}</td>
        <td class="text-end">{{ number_format((float)$row->cash_in, 2) }}</td>
        <td class="text-end">{{ number_format((float)$row->cash_out, 2) }}</td>
        <td>{{ $row->metal_type ? ucfirst($row->metal_type) : '-' }}</td>
        <td class="text-end">{{ number_format((float)$row->metal_in, 3) }}</td>
        <td class="text-end">{{ number_format((float)$row->metal_out, 3) }}</td>
        <td class="text-end">{{ number_format((float)$row->rate, 2) }}</td>
        <td>{{ $row->remarks ?? '-' }}</td>
    </tr>
@empty
    <tr><td colspan="12" class="text-center">No entries found</td></tr>
@endforelse
