<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    /**
     * Admin: bütün ticketlər
     */
    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::with(['partner:id,company_name', 'messages']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('partner_id')) {
            $query->where('partner_id', $request->partner_id);
        }

        $tickets = $query->orderBy('created_at', 'desc')->get();

        // Hər ticket üçün admin-in oxumadığı mesaj sayı
        $tickets->each(function ($ticket) {
            $ticket->unread_count = $ticket->messages->where('sender', 'partner')->whereNull('read_at')->count();
        });

        return response()->json($tickets);
    }

    /**
     * Admin: ticket statistikası + ümumi oxunmamış say
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'open' => SupportTicket::where('status', 'open')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'resolved' => SupportTicket::where('status', 'resolved')->count(),
            'closed' => SupportTicket::where('status', 'closed')->count(),
            'total' => SupportTicket::count(),
            'unread' => TicketMessage::where('sender', 'partner')->whereNull('read_at')->count(),
        ]);
    }

    /**
     * Admin: ticket açdıqda partner mesajlarını "oxundu" et
     */
    public function markRead(int $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);

        TicketMessage::where('ticket_id', $ticket->id)
            ->where('sender', 'partner')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Admin: ticket-ə mesaj yaz + status dəyiş
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'message' => 'nullable|string',
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->update(['status' => $request->status]);

        if ($request->filled('message')) {
            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender' => 'admin',
                'message' => $request->message,
            ]);
        }

        // Admin cavab yazdıqda partner mesajlarını oxundu et
        TicketMessage::where('ticket_id', $ticket->id)
            ->where('sender', 'partner')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json($ticket->load(['partner:id,company_name', 'messages']));
    }

    /**
     * Partner: öz ticketləri
     */
    public function myTickets(Request $request): JsonResponse
    {
        $partner = $request->user();

        $query = SupportTicket::with('messages')->where('partner_id', $partner->id);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $tickets = $query->orderBy('created_at', 'desc')->get();

        // Hər ticket üçün partner-in oxumadığı mesaj sayı
        $tickets->each(function ($ticket) {
            $ticket->unread_count = $ticket->messages->where('sender', 'admin')->whereNull('read_at')->count();
        });

        return response()->json($tickets);
    }

    /**
     * Partner: oxunmamış mesaj sayı
     */
    public function myUnreadCount(Request $request): JsonResponse
    {
        $partner = $request->user();

        $count = TicketMessage::whereHas('ticket', function ($q) use ($partner) {
            $q->where('partner_id', $partner->id);
        })->where('sender', 'admin')->whereNull('read_at')->count();

        return response()->json(['unread' => $count]);
    }

    /**
     * Partner: ticket açdıqda admin mesajlarını "oxundu" et
     */
    public function markReadPartner(Request $request, int $id): JsonResponse
    {
        $partner = $request->user();
        $ticket = SupportTicket::where('id', $id)->where('partner_id', $partner->id)->firstOrFail();

        TicketMessage::where('ticket_id', $ticket->id)
            ->where('sender', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Partner: yeni ticket yarat
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'required|in:support,deposit,rotation,billing,technical,general',
            'priority' => 'nullable|in:low,medium,high',
            'message' => 'required|string',
        ]);

        $partner = $request->user();

        $ticket = SupportTicket::create([
            'partner_id' => $partner->id,
            'subject' => $request->subject,
            'category' => $request->category,
            'priority' => $request->priority ?? 'medium',
            'message' => $request->message,
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender' => 'partner',
            'message' => $request->message,
        ]);

        return response()->json($ticket->load('messages'), 201);
    }

    /**
     * Partner: ticket-ə cavab yaz
     */
    public function partnerReply(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $partner = $request->user();
        $ticket = SupportTicket::where('id', $id)->where('partner_id', $partner->id)->firstOrFail();

        if ($ticket->status === 'closed') {
            return response()->json(['message' => 'Ticket is closed'], 403);
        }

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender' => 'partner',
            'message' => $request->message,
        ]);

        // Admin mesajlarını oxundu et (partner chat-a baxır)
        TicketMessage::where('ticket_id', $ticket->id)
            ->where('sender', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($ticket->status === 'resolved') {
            $ticket->update(['status' => 'open']);
        }

        return response()->json($ticket->load('messages'));
    }
}
