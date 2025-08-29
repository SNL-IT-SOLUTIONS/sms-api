<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Official Receipt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 14px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            margin-top: 30px;
        }
    </style>
</head>

<body>
    <h2>Official Receipt</h2>

    <p><b>Receipt No:</b> {{ $receiptNo ?? 'N/A' }}</p>
    <p><b>Date:</b> {{ $paidAt ? \Carbon\Carbon::parse($paidAt)->format('F d, Y h:i A') : now()->format('F d, Y h:i A') }}</p>
    <hr>

    <p><b>Student Name:</b> {{ $firstName }} {{ $lastName }}</p>
    <p><b>Student Number:</b> {{ $studentNumber }}</p>
    <p><b>Course:</b> {{ $courseName }}</p>
    <p><b>Campus:</b> {{ $campusName }}</p>

    <h2>Subjects</h2>
    <table>
        <tr>
            <th>Code</th>
            <th>Subject Name</th>
            <th>Units</th>
        </tr>
        @foreach ($subjects as $subject)
            <tr>
                <td>{{ $subject->subject_code }}</td>
                <td>{{ $subject->subject_name }}</td>
                <td>{{ $subject->units }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="2"><b>Total Units</b></td>
            <td><b>{{ $totalUnits }}</b></td>
        </tr>
    </table>

    <h2>Payment Details</h2>
    @php
        $tuitionFeeNum = (float) str_replace(',', '', $tuitionFee);
        $miscFeeNum = (float) str_replace(',', '', $miscFee);
        $unitsFeeNum = (float) str_replace(',', '', $unitsFee);
        $totalFee = $tuitionFeeNum + $miscFeeNum + $unitsFeeNum;

        $paidAmountNum = (float) str_replace(',', '', $paidAmount);
        $remainingBalanceNum = (float) str_replace(',', '', $remainingBalance);
    @endphp
    <table>
        <tr>
            <th>Description</th>
            <th>Amount (₱)</th>
        </tr>
        <tr>
            <td>Tuition Fee</td>
            <td>{{ number_format($tuitionFeeNum, 2) }}</td>
        </tr>
        <tr>
            <td>Miscellaneous Fee</td>
            <td>{{ number_format($miscFeeNum, 2) }}</td>
        </tr>
        <tr>
            <td>Units Fee</td>
            <td>{{ number_format($unitsFeeNum, 2) }}</td>
        </tr>
        <tr>
            <td><b>Total Fee</b></td>
            <td><b>{{ number_format($totalFee, 2) }}</b></td>
        </tr>
        <tr>
            <td><b>Paid Amount</b></td>
            <td><b>{{ number_format($paidAmountNum, 2) }}</b></td>
        </tr>
        <tr>
            <td><b>Remaining Balance</b></td>
            <td><b>{{ number_format($remainingBalanceNum, 2) }}</b></td>
        </tr>
    </table>

    <p class="footer">This is a system-generated receipt. No signature required.</p>
</body>

</html>