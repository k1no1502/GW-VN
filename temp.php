<?php
$startMonth = new DateTime(date('Y-m-01', strtotime($start_date)));
$endMonth = new DateTime(date('Y-m-01', strtotime($end_date)));
$donationMonthlyMap = [];
foreach ($donationStats as $row) {
    $donationMonthlyMap[$row['month']] = (int) $row['count'];
}
$donationChartData = [];
if ($startMonth <= $endMonth) {
    $period = new DatePeriod($startMonth, new DateInterval('P1M'), $endMonth->modify('+1 month'));
    foreach ($period as $month) {
        $key = $month->format('Y-m');
        $donationChartData[] = [
            'label' => 'Thang ' . (int) $month->format('n'),
            'value' => $donationMonthlyMap[$key] ?? 0,
            'raw' => $key
        ];
    }
} else {
    $donationChartData = [];
}
?>
