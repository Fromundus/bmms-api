<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Registered Members</title>
    <style>
        @page {
            size: A4 landscape; /* 👈 Makes it landscape */
            margin: 15px;
        }
        body {
            font-family: sans-serif;
            font-size: 10px;
        }
        h2 {
            text-align: center;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        th, td {
            border: 1px solid #333;
            padding: 5px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f2f2f2;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>Registered Members</h2>
    <table>
        <thead>
            <tr>
                <th>Account Number</th>
                <th>Book</th>
                <th>Name</th>
                <th>Address</th>
                <th>Occupant</th>
                <th>ID Presented</th>
                <th>ID Number</th>
                <th>Phone Number</th>
                <th>Email</th>
                <th>Created By</th>
                <th>Area</th>
                <th>Registration Method</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            @foreach($members as $member)
            <tr>
                <td>{{ $member->account_number }}</td>
                <td>{{ $member->book }}</td>
                <td>{{ $member->name }}</td>
                <td>{{ $member->address }}</td>
                <td>{{ $member->occupant }}</td>
                <td>{{ $member->id_presented }}</td>
                <td>{{ $member->id_number }}</td>
                <td>{{ $member->phone_number }}</td>
                <td>{{ $member->email }}</td>
                <td>{{ $member->created_by ?? $member->name }}</td>
                <td>{{ $member->area }}</td>
                <td>{{ $member->registration_method }}</td>
                <td>{{ \Carbon\Carbon::parse($member->created_at)->format('d M Y h:i A') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
