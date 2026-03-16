<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Appointment;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MobileAppointmentController extends Controller
{
  /** List all appointments for the authenticated user */
  public function index()
  {
    $baseUrl = config('filesystems.disks.object-storage.url');

    $appointments = Appointment::where('user_id', Auth::id())
      ->with('result:id,scan_id,breed,confidence,image')
      ->latest()
      ->get()
      ->map(function ($appt) use ($baseUrl) {
        if ($appt->result?->image) {
          $appt->result->image = $baseUrl . '/' . $appt->result->image;
        }
        $appt->appointment_date = $appt->appointment_date
          ? $appt->appointment_date->format('Y-m-d')
          : null;
        return $appt;
      });

    return response()->json(['success' => true, 'appointments' => $appointments]);
  }

  /** User requests an appointment (no scan required) */
  public function request(Request $request)
  {
    $validated = $request->validate([
      'preferred_date' => 'required|date|after_or_equal:today',
      'preferred_time' => 'required|string',
      'reason'         => 'required|string|max:500',
      'notes'          => 'nullable|string|max:1000',
    ]);

    $user = Auth::user();

    $appointment = Appointment::create([
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

    AdminNotification::create([
      'type'             => 'user_appointment_request',
      'message'          => "{$user->name} has requested a clinic appointment via the mobile app.",
      'breed'            => '—',
      'scan_id'          => $appointment->scan_id,
      'appointment_id'   => $appointment->id,
      'appointment_date' => $validated['preferred_date'],
      'appointment_time' => $validated['preferred_time'],
      'vet_name'         => 'To be assigned',
      'rejection_reason' => null,
      'is_read'          => false,
    ]);

    Log::info('✓ Mobile appointment request created', ['appointment_id' => $appointment->id, 'user_id' => $user->id]);

    return response()->json([
      'success' => true,
      'message' => 'Appointment request sent. The clinic will get back to you soon.',
      'data'    => $appointment,
    ], 201);
  }

  /** Accept or reject a clinic-created appointment */
  public function updateStatus(Request $request, Appointment $appointment)
  {
    $user = Auth::user();

    if ($appointment->initiated_by === 'clinic' && $appointment->user_id !== $user->id) {
      return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }
    if ($appointment->initiated_by === 'user') {
      return response()->json(['success' => false, 'message' => 'This is your own request — the clinic will respond.'], 403);
    }

    $validated = $request->validate([
      'status'           => 'required|in:accepted,rejected',
      'rejection_reason' => 'nullable|required_if:status,rejected|string|max:500',
    ]);

    $appointment->update([
      'status'           => $validated['status'],
      'rejection_reason' => $validated['status'] === 'rejected' ? ($validated['rejection_reason'] ?? null) : null,
    ]);

    $breed = optional($appointment->result)->breed ?? 'Unknown Breed';
    AdminNotification::create([
      'type'             => $validated['status'] === 'accepted' ? 'appointment_accepted' : 'appointment_rejected',
      'message'          => $validated['status'] === 'accepted'
        ? "The owner confirmed the appointment for their {$breed} (mobile)."
        : "The owner declined the appointment for their {$breed} (mobile).",
      'breed'            => $breed,
      'scan_id'          => $appointment->scan_id,
      'appointment_id'   => $appointment->id,
      'appointment_date' => $appointment->appointment_date,
      'appointment_time' => $appointment->appointment_time,
      'vet_name'         => $appointment->vet_name,
      'rejection_reason' => $validated['status'] === 'rejected' ? ($validated['rejection_reason'] ?? null) : null,
      'is_read'          => false,
    ]);

    // Also notify user if their request was approved
    if ($appointment->initiated_by === 'user' && $validated['status'] !== null) {
      Notification::create([
        'user_id' => $appointment->user_id,
        'type'    => $validated['status'] === 'accepted' ? 'appointment_accepted' : 'appointment_rejected',
        'title'   => $validated['status'] === 'accepted' ? 'Appointment Approved' : 'Appointment Declined',
        'message' => $validated['status'] === 'accepted'
          ? "Your appointment on {$appointment->appointment_date} at {$appointment->appointment_time} has been approved."
          : "Your appointment request has been declined." . ($validated['rejection_reason'] ? " Reason: {$validated['rejection_reason']}" : ''),
        'data'    => ['appointment_id' => $appointment->id, 'link' => '/appointments'],
        'read'    => false,
      ]);
    }

    return response()->json([
      'success' => true,
      'message' => $validated['status'] === 'accepted'
        ? 'Appointment confirmed. The clinic has been notified.'
        : 'Appointment declined. The clinic has been notified.',
      'data'    => $appointment->fresh(),
    ]);
  }

  /** Delete an appointment */
  public function destroy(Appointment $appointment)
  {
    $user = Auth::user();

    $canDelete =
      ($appointment->initiated_by === 'user'   && $appointment->user_id === $user->id) ||
      ($appointment->initiated_by === 'clinic' && $appointment->user_id === $user->id && $appointment->status !== 'pending');

    if (!$canDelete) {
      return response()->json([
        'success' => false,
        'message' => $appointment->status === 'pending'
          ? 'Please accept or decline before deleting.'
          : 'You cannot delete this appointment.',
      ], 403);
    }

    $appointment->delete();
    return response()->json(['success' => true, 'message' => 'Appointment deleted.']);
  }
}
