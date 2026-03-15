<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
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

        $result = Results::findOrFail($validated['result_id']);

        $appointment = Appointment::create([
            'scan_id'          => $validated['scan_id'],
            'result_id'        => $validated['result_id'],
            'user_id'          => $result->user_id,
            'created_by'       => Auth::id(),
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
        ]);

        return redirect("/model/review-dog/{$result->id}")
            ->with('success', 'Appointment scheduled. The owner has been notified.');
    }

    /**
     * Normal user accepts or rejects an appointment.
     * Fires an AdminNotification so the bell icon updates.
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        // Only the dog owner can respond
        if ($appointment->user_id !== Auth::id()) {
            abort(403);
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

        // ── Fire admin notification ───────────────────────────────────────────
        $breed = optional($appointment->result)->breed ?? 'Unknown Breed';

        AdminNotification::create([
            'type'             => $validated['status'] === 'accepted'
                ? 'appointment_accepted'
                : 'appointment_rejected',
            'message'          => $validated['status'] === 'accepted'
                ? "The owner confirmed the appointment for their {$breed}."
                : "The owner declined the appointment for their {$breed}.",
            'breed'            => $breed,
            'scan_id'          => $appointment->scan_id,
            'appointment_id'   => $appointment->id,
            'appointment_date' => $appointment->appointment_date,
            'appointment_time' => $appointment->appointment_time,
            'vet_name'         => $appointment->vet_name,
            'rejection_reason' => $validated['status'] === 'rejected'
                ? ($validated['rejection_reason'] ?? null)
                : null,
            'is_read'          => false,
        ]);

        Log::info('✓ Appointment status updated + admin notified', [
            'appointment_id' => $appointment->id,
            'status'         => $validated['status'],
        ]);

        return redirect()->back()->with(
            'success',
            $validated['status'] === 'accepted'
                ? 'Appointment confirmed. The clinic has been notified.'
                : 'Appointment declined. The clinic has been notified.'
        );
    }

    /**
     * Normal user — list their appointments.
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

        return Inertia::render('normal_user/appointments', [
            'appointments' => $appointments,
        ]);
    }
}
