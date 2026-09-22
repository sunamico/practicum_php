<?php
class Room {
    protected string $number;
    protected int $capacity;
    protected float $pricePerNight;
    protected bool $isBooked;

    public function __construct(string $number, int $capacity, float $pricePerNight, bool $isBooked = false) {
        $this->number = $number;
        $this->capacity = $capacity;
        $this->pricePerNight = $pricePerNight;
        $this->isBooked = $isBooked;
    }

    public function getInfo(): string {
        $statusClass = $this->isBooked ? 'status-booked' : 'status-available';
        $statusText = $this->isBooked ? 'Заброньовано' : 'Вільний';
        
        $statusHtml = "<span class='status-badge {$statusClass}'>{$statusText}</span>";
        $priceHtml = "<span class='price-tag'>{$this->pricePerNight} грн/ніч</span>";

        return "Номер <b>{$this->number}</b> (Місткість: {$this->capacity} ос., Ціна: {$priceHtml})  {$statusHtml}";
    }

    public function isAvailable(): bool {
        return !$this->isBooked;
    }

    public function getPrice(): float {
        return $this->pricePerNight;
    }
}