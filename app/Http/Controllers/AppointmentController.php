<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Results;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AppointmentController extends Controller
{
    /**
     * Admin/Vet creates an appointment for a scanned dog.
     * Notifies the normal user (dog owner).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'scan_id'          => 'required|string|exists:results,scan_id',
            'result_id'        => 'required|integer|exists:results,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|string',
            'vet_name'         => 'required|string|max:255',
            'reason'           => 'required|string|max:500',
            'notes'            => 'nullable|string|max:1000',
        ]);

        // Fetch the result to get the dog owner's user_id
        $result = Results::findOrFail($validated['result_id']);

        $appointment = Appointment::create([
            'scan_id'          => $validated['scan_id'],
            'result_id'        => $validated['result_id'],
            'user_id'          => $result->user_id,       // dog owner
            'created_by'       => Auth::id(),              // admin/vet
            'appointment_date' => $validated['appointment_date'],
            'appointment_time' => $validated['appointment_time'],
            'vet_name'         => $validated['vet_name'],
            'reason'           => $validated['reason'],
            'notes'            => $validated['notes'] ?? null,
            'status'           => 'pending',
        ]);

        Log::info('✓ Appointment created', [
            'appointment_id' => $appointment->id,
            'scan_id'        => $appointment->scan_id,
            'user_id'        => $appointment->user_id,
            'date'           => $appointment->appointment_date,
        ]);

        // Redirect back to the review page so the status card shows up
        return redirect("/model/review-dog/{$result->id}")
            ->with('success', 'Appointment scheduled. The owner has been notified.');
    }

    /**
     * Normal user updates the appointment status (accepted / rejected).
     * The vet/admin side will see the updated status on the review page.
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        // Ensure the request is from the dog owner
        if ($appointment->user_id !== Auth::id()) {
            abort(403, 'You are not authorised to respond to this appointment.');
        }

        $validated = $request->validate([
            'status'           => 'required|in:accepted,rejected',
            'rejection_reason' => 'nullable|required_if:status,rejected|string|max:500',
        ]);

        $appointment->update([
            'status'           => $validated['status'],
            'rejection_reason' => $validated['status'] === 'rejected'
                ? ($validated['rejection_reason'] ?? null)
                : null,
        ]);

        Log::info('✓ Appointment status updated by owner', [
            'appointment_id' => $appointment->id,
            'status'         => $validated['status'],
            'user_id'        => Auth::id(),
        ]);

        return redirect()->back()->with(
            'success',
            $validated['status'] === 'accepted'
                ? 'Appointment confirmed. The clinic has been notified.'
                : 'Appointment declined. The clinic has been notified.'
        );
    }

    /**
     * Normal user: list all appointments for their scans.
     */
    public function userIndex()
    {
        $appointments = Appointment::where('user_id', Auth::id())
            ->with('result:id,scan_id,breed,confidence,image')
            ->latest()
            ->get()
            ->map(function ($appt) {
                $baseUrl = config('filesystems.disks.object-storage.url');
                if ($appt->result) {
                    $appt->result->image = $baseUrl . '/' . $appt->result->image;
                }
                return $appt;
            });

        return Inertia::render('normal_user/UserAppointments', [
            'appointments' => $appointments,
        ]);
    }
}