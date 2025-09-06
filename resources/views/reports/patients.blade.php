<!-- resources/views/reports/patients.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Patients Report</title>
    <style>
        table { width: 100%; border-collapse: collapse; }
        table, th, td { border: 1px solid black; padding: 5px; }
    </style>
</head>
<body>
    <h2>Patients Report</h2>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Sex</th>
                <th>Birthday</th>
                <th>Age</th>
                <th>Weight</th>
                <th>Height</th>
                <th>Weight for Age</th>
                <th>Height for Age</th>
                <th>Weight for Lt/Ht Status</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($patients as $patient)
            <tr>
                <td>{{ $patient->name }}</td>
                <td>{{ $patient->sex }}</td>
                <td>{{ $patient->birthday }}</td>
                <td>{{ $patient->age }}</td>
                <td>{{ $patient->weight }}</td>
                <td>{{ $patient->height }}</td>
                <td>{{ $patient->weight_for_age }}</td>
                <td>{{ $patient->height_for_age }}</td>
                <td>{{ $patient->weight_for_ltht_status }}</td>
                <td>{{ $patient->status }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
