<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Sex</th>
            <th>Date Measured</th>
            <th>Age</th>
            <th>Weight</th>
            <th>Height</th>
            <th>Weight for Age</th>
            <th>Height for Age</th>
            <th>Weight for ltht Status</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($records as $record)
            <tr>
                <td>{{ $record->patient->name }}</td>
                <td>{{ $record->patient->sex }}</td>
                <td>{{ $record->date_measured }}</td>
                <td>{{ $record->age }}</td>
                <td>{{ $record->weight }}</td>
                <td>{{ $record->height }}</td>
                <td>{{ $record->weight_for_age }}</td>
                <td>{{ $record->height_for_age }}</td>
                <td>{{ $record->weight_for_ltht_status }}</td>
                <td>{{ $record->status }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
