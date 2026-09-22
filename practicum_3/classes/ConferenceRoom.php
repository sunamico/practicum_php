<?php
require_once 'Room.php';

class ConferenceRoom extends Room {
    private array $equipmentList;

    public function __construct(string $number, int $capacity, float $pricePerNight, array $equipmentList, bool $isBooked = false) {
        parent::__construct($number, $capacity, $pricePerNight, $isBooked);
        $this->equipmentList = $equipmentList;
    }

    public function getInfo(): string {
        $baseInfo = parent::getInfo();
        
        $baseInfo = str_replace('Номер ', '', $baseInfo);
        
        $equipment = implode(', ', $this->equipmentList);
        
        return "<strong>Конференц-зал</strong> {$baseInfo}<em>{$equipment}</em>";
    }
}