<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Statement of Account</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; font-size: 14px; }
        h2 { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .footer { text-align: center; font-size: 12px; margin-top: 30px; }
    </style>
</head>
<body>
    <h2>Statement of Account</h2>
    <p><b>Student Name:</b> {{ $firstName }} {{ $lastName }}</p>
    <p><b>Student Number:</b> {{ $studentNumber }}</p>
    <p><b>Course:</b> {{ $courseName }}</p>
    <p><b>Campus:</b> {{ $campusName }}</p>

    <table>
        <tr>
            <th>Description</th>
            <th>Amount (₱)</th>
        </tr>
      
        <tr>
            <td>Miscellaneous Fee</td>
            <td>{{ $miscFee }}</td>
        </tr>
        <tr>
            <td>Units Fee</td>
            <td>{{ $unitsFee }}</td>
        </tr>
          <tr>
            <td>Tuition Fee</td>
            <td>{{ $tuitionFee }}</td>
        </tr>
    </table>

    <p class="footer">Please note that this is a sample receipt and should not be used for any financial transactions.</p>
</body>
</html>
