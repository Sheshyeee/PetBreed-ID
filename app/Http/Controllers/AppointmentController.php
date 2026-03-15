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
     * Admin/Vet creates an appointment (clinic-initiated).
     * Fires a Notification to the dog owner.
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
            'initiated_by'     => 'clinic',
            'appointment_date' => $validated['appointment_date'],
            'appointment_time' => $validated['appointment_time'],
            'vet_name'         => $validated['vet_name'],
            'reason'           => $validated['reason'],
            'notes'            => $validated['notes'] ?? null,
            'status'           => 'pending',
        ]);

        // Notify the dog owner
        $breed   = $result->breed ?? 'your dog';
        $baseUrl = config('filesystems.disks.object-storage.url');

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
                'image'            => $result->image ? $baseUrl . '/' . $result->image : null,
                'link'             => '/appointments',
            ],
            'read' => false,
        ]);

        return redirect("/model/review-dog/{$result->id}")
            ->with('success', 'Appointment scheduled. The owner has been notified.');
    }

    /**
     * Normal user requests an appointment (user-initiated).
     * No scan required — free-form request sent to admin for approval.
     * Admin responds via updateStatus().
     */
    public function userRequest(Request $request)
    {
        $validated = $request->validate([
            'preferred_date' => 'required|date|after_or_equal:today',
            'preferred_time' => 'required|string',
            'reason'         => 'required|string|max:500',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();

        $appointment = Appointment::create([
            // No scan_id / result_id — user-initiated has no linked scan
            'scan_id'          => 'USER-REQ-' . strtoupper(substr(uniqid(), -6)),
            'result_id'        => null,
            'user_id'          => $user->id,
            'created_by'       => $user->id,
            'initiated_by'     => 'user',
            'appointment_date' => $validated['preferred_date'],
            'appointment_time' => $validated['preferred_time'],
            'vet_name'         => 'To be assigned',
            'reason'           => $validated['reason'],
            'notes'            => $validated['notes'] ?? null,
            'status'           => 'pending',
        ]);

        // Notify admin via AdminNotification so the bell rings
        AdminNotification::create([
            'type'             => 'user_appointment_request',
            'message'          => "{$user->name} has requested a clinic appointment.",
            'breed'            => '—',
            'scan_id'          => $appointment->scan_id,
            'appointment_id'   => $appointment->id,
            'appointment_date' => $validated['preferred_date'],
            'appointment_time' => $validated['preferred_time'],
            'vet_name'         => 'To be assigned',
            'rejection_reason' => null,
            'is_read'          => false,
        ]);

        Log::info('✓ User appointment request created', [
            'appointment_id' => $appointment->id,
            'user_id'        => $user->id,
        ]);

        return redirect()->back()->with('success', 'Appointment request sent. The clinic will get back to you soon.');
    }

    /**
     * Status update — works for both directions:
     *  - Normal user accepts/rejects a clinic-created appointment
     *  - Admin accepts/rejects a user-created appointment request
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        $user = Auth::user();

        // Determine who is allowed to respond
        // If clinic created it → owner responds
        // If user created it → admin responds (any admin user)
        $allowedEmails = ['modeltraining2000@gmail.com', 'jrbd2022-8800-57025@bicol-u.edu.ph', 'dmbc2022-2141-53989@bicol-u.edu.ph', 'asvermudo@gmail.com'];
        $isAdmin = in_array($user->email, $allowedEmails);

        if ($appointment->initiated_by === 'clinic' && $appointment->user_id !== $user->id) {
            abort(403);
        }

        if ($appointment->initiated_by === 'user' && !$isAdmin) {
            abort(403);
        }

        $validated = $request->validate([
            'status'           => 'required|in:accepted,rejected',
            'rejection_reason' => 'nullable|required_if:status,rejected|string|max:500',
            'vet_name'         => 'nullable|string|max:255', // admin can assign vet when accepting
        ]);

        $updateData = [
            'status'           => $validated['status'],
            'rejection_reason' => $validated['status'] === 'rejected'
                ? ($validated['rejection_reason'] ?? null)
                : null,
        ];

        // Admin assigning vet when accepting a user request
        if ($appointment->initiated_by === 'user' && $validated['status'] === 'accepted' && !empty($validated['vet_name'])) {
            $updateData['vet_name'] = $validated['vet_name'];
        }

        $appointment->update($updateData);

        // ── Fire appropriate notification ─────────────────────────────────────
        if ($appointment->initiated_by === 'clinic') {
            // Owner responded → notify admin
            $breed = optional($appointment->result)->breed ?? 'Unknown Breed';
            AdminNotification::create([
                'type'             => $validated['status'] === 'accepted' ? 'appointment_accepted' : 'appointment_rejected',
                'message'          => $validated['status'] === 'accepted'
                    ? "The owner confirmed the appointment for their {$breed}."
                    : "The owner declined the appointment for their {$breed}.",
                'breed'            => $breed,
                'scan_id'          => $appointment->scan_id,
                'appointment_id'   => $appointment->id,
                'appointment_date' => $appointment->appointment_date,
                'appointment_time' => $appointment->appointment_time,
                'vet_name'         => $appointment->vet_name,
                'rejection_reason' => $validated['status'] === 'rejected' ? ($validated['rejection_reason'] ?? null) : null,
                'is_read'          => false,
            ]);
        } else {
            // Admin responded to user request → notify user
            Notification::create([
                'user_id' => $appointment->user_id,
                'type'    => $validated['status'] === 'accepted' ? 'appointment_accepted' : 'appointment_rejected',
                'title'   => $validated['status'] === 'accepted' ? 'Appointment Request Approved' : 'Appointment Request Declined',
                'message' => $validated['status'] === 'accepted'
                    ? "Your appointment request on {$appointment->appointment_date} at {$appointment->appointment_time} has been approved."
                    : "Your appointment request has been declined. " . ($validated['rejection_reason'] ? "Reason: {$validated['rejection_reason']}" : ''),
                'data'    => [
                    'appointment_id'   => $appointment->id,
                    'appointment_date' => $appointment->appointment_date,
                    'appointment_time' => $appointment->appointment_time,
                    'vet_name'         => $appointment->vet_name,
                    'link'             => '/appointments',
                ],
                'read' => false,
            ]);
        }

        $successMsg = $appointment->initiated_by === 'clinic'
            ? ($validated['status'] === 'accepted' ? 'Appointment confirmed. The clinic has been notified.' : 'Appointment declined. The clinic has been notified.')
            : ($validated['status'] === 'accepted' ? 'Request approved. The owner has been notified.' : 'Request declined. The owner has been notified.');

        return redirect()->back()->with('success', $successMsg);
    }

    /**
     * Normal user — list their appointments (both clinic-created and user-requested).
     */
    public function userIndex()
    {
        $baseUrl = config('filesystems.disks.object-storage.url');

        $appointments = Appointment::where('user_id', Auth::id())
            ->with('result:id,scan_id,breed,confidence,image')
            ->latest()
            ->get()
            ->map(function ($appt) use ($baseUrl) {
                if ($appt->result) {
                    $appt->result->image = $baseUrl . '/' . $appt->result->image;
                }
                return $appt;
            });

        return Inertia::render('normal_user/appointments', [
            'appointments' => $appointments,
        ]);
    }

    /**
     * Admin — list all appointments split by who initiated them.
     */
    public function adminIndex()
    {
        $baseUrl = config('filesystems.disks.object-storage.url');

        // Clinic-initiated: paginate
        $clinicAppts = Appointment::where('initiated_by', 'clinic')
            ->with('result:id,scan_id,breed,image')
            ->latest()
            ->paginate(15, ['*'], 'clinic_page');

        $clinicAppts->getCollection()->transform(function ($appt) use ($baseUrl) {
            if ($appt->result?->image) {
                $appt->result->image = $baseUrl . '/' . $appt->result->image;
            }
            return $appt;
        });

        // User-initiated: paginate separately
        $userAppts = Appointment::where('initiated_by', 'user')
            ->with('owner:id,name,email')
            ->latest()
            ->paginate(15, ['*'], 'user_page');

        $stats = [
            'total'            => Appointment::count(),
            'pending'          => Appointment::where('status', 'pending')->count(),
            'accepted'         => Appointment::where('status', 'accepted')->count(),
            'rejected'         => Appointment::where('status', 'rejected')->count(),
            'user_requests'    => Appointment::where('initiated_by', 'user')->count(),
            'clinic_scheduled' => Appointment::where('initiated_by', 'clinic')->count(),
        ];

        return Inertia::render('model/appointments-page', [
            'clinicAppointments' => $clinicAppts,
            'userAppointments'   => $userAppts,
            'stats'              => $stats,
        ]);
    }

    /**
     * Delete an appointment (only allowed after a decision has been made).
     */
    public function destroy(Appointment $appointment)
    {
        if ($appointment->status === 'pending') {
            return redirect()->back()->with('error', 'Cannot delete a pending appointment.');
        }
        $appointment->delete();
        return redirect()->back()->with('success', 'Appointment deleted.');
    }
}