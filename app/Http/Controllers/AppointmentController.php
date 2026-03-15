<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Appointment;
use App\Models\Notification;
use App\Models\Results;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AppointmentController extends Controller
{
    /**
     * Admin/Vet creates an appointment.
     * Fires a Notification to the dog owner so the bell badge appears.
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

        // ── Notify the dog owner via the existing Notification system ─────────
        $breed = $result->breed ?? 'your dog';
        $baseUrl = config('filesystems.disks.object-storage.url');
        $imageUrl = $result->image ? $baseUrl . '/' . $result->image : null;

        Notification::create([
            'user_id' => $result->user_id,
            'type'    => 'appointment_scheduled',
            'title'   => 'Clinic Appointment Scheduled',
            'message' => "Your {$breed} has been scheduled for a consultation on {$validated['appointment_date']} at {$validated['appointment_time']} with {$validated['vet_name']}.",
            'data'    => [
                'scan_id'          => $validated['scan_id'],
                'appointment_id'   => $appointment->id,
                'breed'            => $breed,
                'appointment_date' => $validated['appointment_date'],
                'appointment_time' => $validated['appointment_time'],
                'vet_name'         => $validated['vet_name'],
                'reason'           => $validated['reason'],
                'image'            => $imageUrl,
                'link'             => '/appointments',
            ],
            'read'    => false,
        ]);

        Log::info('✓ Appointment created + owner notified', [
            'appointment_id' => $appointment->id,
            'scan_id'        => $appointment->scan_id,
            'user_id'        => $result->user_id,
        ]);

        return redirect("/model/review-dog/{$result->id}")
            ->with('success', 'Appointment scheduled. The owner has been notified.');
    }

    /**
     * Normal user accepts or rejects.
     * Fires an AdminNotification so the admin bell updates.
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
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

        return Inertia::render('normal_user/UserAppointments', [
            'appointments' => $appointments,
        ]);
    }

    public function show(){
         {
        $baseUrl = config('filesystems.disks.object-storage.url');
 
        $appointments = Appointment::with('result:id,scan_id,breed,image')
            ->latest()
            ->paginate(15);
 
        $appointments->getCollection()->transform(function ($appt) use ($baseUrl) {
            if ($appt->result?->image) {
                $appt->result->image = $baseUrl . '/' . $appt->result->image;
            }
            return $appt;
        });
 
        $stats = [
            'total'    => Appointment::count(),
            'pending'  => Appointment::where('status', 'pending')->count(),
            'accepted' => Appointment::where('status', 'accepted')->count(),
            'rejected' => Appointment::where('status', 'rejected')->count(),
        ];
 
        return inertia('model/appointments-page', [
            'appointments' => $appointments,
            'stats'        => $stats,
        ]);
    }
    }
}
