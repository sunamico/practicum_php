<?php
require_once 'Room.php';

class BookingSystem {
    private array $rooms = [];

    public function addRoom(Room $room): void {
        $this->rooms[] = $room;
    }

    public function findAvailable(): array {
        $availableRooms = [];
        foreach ($this->rooms as $room) {
            if ($room->isAvailable()) {
                $availableRooms[] = $room;
            }
        }
        return $availableRooms;
    }

    public function calculateTotal(int $nights): float {
        $total = 0;
        foreach ($this->rooms as $room) {
            if (!$room->isAvailable()) {
                $total += $room->getPrice() * $nights;
            }
        }
        return $total;
    }

    public function getAllRooms(): array {
        return $this->rooms;
    }
}