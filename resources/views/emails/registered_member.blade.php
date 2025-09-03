<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>AGMA 2025 Registration Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f3f4f6; padding: 20px; color: #333;">

    <div style="max-width: 700px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <!-- Heading -->
        <h1 style="font-size: 28px; color: #3b82f6; text-align: center; margin-bottom: 20px;">CONGRATULATIONS!</h1>

        <!-- Image -->
        {{-- <div style="text-align: center; margin-bottom: 20px;">
            <img src="https://www.svgrepo.com/show/397713/party-popper.svg" alt="Celebration" style="max-width: 200px; width: 100%;" />
        </div> --}}

        <!-- Message -->
        <div style="font-size: 14px; line-height: 1.6; margin-bottom: 20px;">
            <p>You have successfully registered to <strong>AGMA 2025</strong>.</p>
            <p style="font-weight: 600;">Please keep this Reference Number as this will serve as your certificate of registration.</p>
        </div>

        <!-- Reference Number -->
        <div style="text-align: center; margin: 20px 0;">
            <p style="font-size: 22px; font-weight: bold; color: #16a34a;">{{ $registeredMember->reference_number }}</p>
        </div>

        <!-- Footer Note -->
        <p style="font-size: 13px; color: #555;">Thank you for registering. We wish you the best of luck in the raffle draw!</p>

        <!-- Member Details -->
        <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 14px;">
            <p style="font-weight: 600; margin-bottom: 10px;">MEMBER DETAILS</p>
            <p><strong>Account Number:</strong> {{ $registeredMember->account_number }}</p>
            <p><strong>Book:</strong> {{ $registeredMember->book }}</p>
            <p><strong>Name:</strong> {{ $registeredMember->name }}</p>
            <p><strong>Address:</strong> {{ $registeredMember->address }}</p>
            <p><strong>Occupant:</strong> {{ $registeredMember->occupant }}</p>
            <p><strong>ID Presented:</strong> {{ $registeredMember->id_presented }}</p>
            <p><strong>ID Number:</strong> {{ $registeredMember->id_number }}</p>
            <p><strong>Phone Number:</strong> {{ $registeredMember->phone_number }}</p>
            <p><strong>Email:</strong> {{ $registeredMember->email }}</p>
            <p><strong>Reference Number:</strong> {{ $registeredMember->reference_number }}</p>
        </div>
    </div>

</body>
</html>
