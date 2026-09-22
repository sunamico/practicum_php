<?php
function formatDateRange(string $checkIn, string $checkOut): string {
    $in = date('d.m.Y', strtotime($checkIn));
    $out = date('d.m.Y', strtotime($checkOut));
    return "{$in} — {$out}";
}

function nightsBetween(string $checkIn, string $checkOut): int {
    $in = new DateTime($checkIn);
    $out = new DateTime($checkOut);
    $interval = $in->diff($out);
    return $interval->days > 0 ? $interval->days : 1;
}