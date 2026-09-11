<?php

namespace App\Services\Attendance;

use App\Models\Branch;
use App\Support\Coordinates;
use App\Support\GeoDistance;
use Illuminate\Validation\ValidationException;

/**
 * Decides whether one clock event happened close enough to the shop.
 *
 * Everything the browser sends is treated as a claim: the distance is always
 * recomputed here from the coordinates and the branch's own position, and a
 * claim that fails is refused rather than stored.
 */
final class GpsVerifier
{
    /**
     * @return int distance from the branch in whole metres
     *
     * @throws ValidationException
     */
    public function verify(Branch $branch, Coordinates $point, int $accuracyMeters): int
    {
        $origin = $branch->coordinates();

        if (! $branch->gps_attendance_enabled || $origin === null) {
            throw ValidationException::withMessages([
                'gps' => 'Chi nhánh này chưa bật chấm công GPS. Hãy báo quản lý cấu hình vị trí cửa hàng.',
            ]);
        }

        $accuracyLimit = (int) $branch->attendance_accuracy_limit_meters;

        if ($accuracyMeters > $accuracyLimit) {
            throw ValidationException::withMessages([
                'gps' => sprintf(
                    'Tín hiệu GPS đang lệch tới %d mét, vượt mức cho phép %d mét. Hãy ra chỗ thoáng rồi thử lại.',
                    $accuracyMeters,
                    $accuracyLimit,
                ),
            ]);
        }

        $distance = GeoDistance::roundedMeters($origin, $point);
        $radius = (int) $branch->attendance_radius_meters;

        if ($distance > $radius) {
            throw ValidationException::withMessages([
                'gps' => sprintf(
                    'Bạn đang cách cửa hàng khoảng %d mét, ngoài phạm vi cho phép %d mét. Hãy tới nơi làm việc rồi chấm công.',
                    $distance,
                    $radius,
                ),
            ]);
        }

        return $distance;
    }
}
