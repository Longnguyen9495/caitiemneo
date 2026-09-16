<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\CheckInAction;
use App\Actions\Attendance\CheckOutAction;
use App\Http\Requests\ClockEventRequest;
use App\Services\Attendance\ShiftBoard;
use App\Services\Calendar\PersonalCalendarQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The employee's own clock-in screen.
 *
 * Kept out of the admin CRUD on purpose: this is the one attendance surface
 * a plain employee reaches, and it can only ever act on the signed-in account.
 */
class StaffAttendanceController extends Controller
{
    public function __construct(private ShiftBoard $shiftBoard) {}

    public function index(Request $request, PersonalCalendarQuery $calendarQuery): View
    {
        $data = $this->shiftBoard->for($request->user());
        $data['calendar'] = $calendarQuery->for(
            $request->user(),
            $request->query('month'),
            route('attendance.board'),
        );

        return view('attendance.board', $data);
    }

    public function checkIn(ClockEventRequest $request, CheckInAction $checkIn): RedirectResponse
    {
        $employee = $request->user();
        $assignment = $this->shiftBoard->actionableAssignment($employee);

        if ($assignment === null) {
            throw ValidationException::withMessages([
                'gps' => 'Hôm nay bạn chưa được phân ca nào đang tới giờ chấm công.',
            ]);
        }

        $record = $checkIn->handle(
            $employee,
            $assignment,
            $request->coordinates(),
            $request->accuracyMeters(),
            $request->clientFingerprint(),
        );

        return redirect()
            ->route('attendance.board')
            ->with('success', sprintf('Đã vào ca %s lúc %s.', $record->shift_name, $record->checked_in_at->format('H:i')));
    }

    public function checkOut(ClockEventRequest $request, CheckOutAction $checkOut): RedirectResponse
    {
        $employee = $request->user();
        $open = $this->shiftBoard->openRecord($employee);

        if ($open === null) {
            throw ValidationException::withMessages([
                'gps' => 'Bạn chưa vào ca nên chưa thể ra ca.',
            ]);
        }

        $record = $checkOut->handle(
            $employee,
            $open,
            $request->coordinates(),
            $request->accuracyMeters(),
            $request->clientFingerprint(),
        );

        $message = $record->overtime_minutes > 0
            ? sprintf('Đã ra ca lúc %s. Vượt ca %d phút đang chờ quản lý duyệt.', $record->checked_out_at->format('H:i'), $record->overtime_minutes)
            : sprintf('Đã ra ca lúc %s.', $record->checked_out_at->format('H:i'));

        return redirect()->route('attendance.board')->with('success', $message);
    }
}
