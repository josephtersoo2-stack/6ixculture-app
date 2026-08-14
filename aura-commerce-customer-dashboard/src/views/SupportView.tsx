import React, { useState } from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  Headphones, 
  MessageSquare, 
  HelpCircle, 
  Phone, 
  Mail, 
  Send, 
  ChevronDown, 
  ChevronUp, 
  Clock, 
  CheckCircle2, 
  AlertCircle, 
  Plus, 
  Sparkles,
  ShieldCheck,
  X
} from 'lucide-react';
import { FAQ_ITEMS } from '../data/mockData';
import { SupportTicket } from '../types/dashboard';

export const SupportView: React.FC = () => {
  const {
    supportTickets,
    createSupportTicket,
    orders,
    showToast
  } = useDashboard();

  // Chat simulator state
  const [messages, setMessages] = useState([
    { id: '1', sender: 'agent', name: 'Elena Vance (VIP Concierge)', time: '10:14 AM', text: 'Good morning Alexander! How can I assist you today with your orders or VIP benefits?' }
  ]);
  const [inputMessage, setInputMessage] = useState('');
  const [isTyping, setIsTyping] = useState(false);

  // FAQ accordion state
  const [openFaqIndex, setOpenFaqIndex] = useState<number | null>(0);
  const [selectedFaqCategory, setSelectedFaqCategory] = useState<'All' | 'Orders' | 'Returns' | 'Shipping' | 'Rewards'>('All');

  // New Ticket Modal
  const [isNewTicketOpen, setIsNewTicketOpen] = useState(false);
  const [ticketSubject, setTicketSubject] = useState('');
  const [ticketCategory, setTicketCategory] = useState<'order_issue' | 'return_inquiry' | 'product_question' | 'billing' | 'other'>('order_issue');
  const [ticketOrderId, setTicketOrderId] = useState(orders[0]?.orderNumber || '');
  const [ticketMessage, setTicketMessage] = useState('');

  const handleSendMessage = (e: React.FormEvent) => {
    e.preventDefault();
    if (!inputMessage.trim()) return;

    const userMsg = {
      id: Date.now().toString(),
      sender: 'user',
      name: 'Alexander Hayes',
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      text: inputMessage.trim()
    };

    setMessages((prev) => [...prev, userMsg]);
    setInputMessage('');
    setIsTyping(true);

    // Simulate concierge intelligent auto-reply
    setTimeout(() => {
      let reply = "I am on it right away! Let me look into that parcel status and make sure everything is handled with priority for you.";
      const lower = userMsg.text.toLowerCase();
      if (lower.includes('return') || lower.includes('exchange')) {
        reply = "Returns are completely complimentary for VIP members! You can generate an instant prepaid label from the Orders tab or I can email you one directly.";
      } else if (lower.includes('shipping') || lower.includes('track')) {
        reply = "Your order #AUR-984102 is currently out for delivery via FedEx Priority with arrival scheduled by 4:30 PM today!";
      } else if (lower.includes('point') || lower.includes('voucher') || lower.includes('reward')) {
        reply = "You currently have 3,450 points in your account! You can redeem them in the VIP Rewards tab for $15, $35, or overnight shipping vouchers.";
      }

      setMessages((prev) => [
        ...prev,
        {
          id: (Date.now() + 1).toString(),
          sender: 'agent',
          name: 'Elena Vance (VIP Concierge)',
          time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          text: reply
        }
      ]);
      setIsTyping(false);
    }, 1200);
  };

  const handleCreateTicketSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!ticketSubject || !ticketMessage) return;

    createSupportTicket(ticketSubject, ticketCategory, ticketMessage, ticketOrderId);
    setIsNewTicketOpen(false);
    setTicketSubject('');
    setTicketMessage('');
  };

  const filteredFaqs = selectedFaqCategory === 'All'
    ? FAQ_ITEMS
    : FAQ_ITEMS.filter(f => f.category.toLowerCase() === selectedFaqCategory.toLowerCase());

  return (
    <div className="space-y-6 max-w-7xl mx-auto pb-12">
      
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-display font-bold text-slate-900 tracking-tight">
            Support Concierge & Help Center
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-0.5">
            24/7 dedicated assistance, live messaging, ticket tracking, and self-serve guides.
          </p>
        </div>

        <button
          onClick={() => setIsNewTicketOpen(true)}
          className="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold flex items-center gap-2 shadow-xs transition-colors self-start sm:self-auto"
        >
          <Plus className="w-4 h-4" />
          <span>Open Support Ticket</span>
        </button>
      </div>

      {/* 3 VIP Contact Channels Banner */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="p-5 rounded-3xl bg-white border border-slate-200/90 shadow-sm flex items-center gap-3.5">
          <div className="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
            <MessageSquare className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs font-bold text-slate-900">Live Concierge Chat</p>
            <p className="text-[11px] text-slate-500">Typical response under 2 mins</p>
            <span className="text-[10px] font-bold text-emerald-600 flex items-center gap-1 mt-0.5">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Online Now
            </span>
          </div>
        </div>

        <div className="p-5 rounded-3xl bg-white border border-slate-200/90 shadow-sm flex items-center gap-3.5">
          <div className="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
            <Phone className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs font-bold text-slate-900">VIP Phone Priority</p>
            <p className="text-[11px] text-slate-500 font-mono">+1 (800) 840-AURA</p>
            <span className="text-[10px] font-semibold text-slate-400 mt-0.5">Toll-free 24/7/365</span>
          </div>
        </div>

        <div className="p-5 rounded-3xl bg-white border border-slate-200/90 shadow-sm flex items-center gap-3.5">
          <div className="w-10 h-10 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
            <Mail className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs font-bold text-slate-900">Priority Email Desk</p>
            <p className="text-[11px] text-slate-500 font-mono">vip@aura.luxury</p>
            <span className="text-[10px] font-semibold text-slate-400 mt-0.5">Guaranteed reply &lt; 1 hour</span>
          </div>
        </div>
      </div>

      {/* Grid: Live Chat Simulator + Active Support Tickets */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        {/* Live Concierge Chat Widget */}
        <div className="bg-white rounded-3xl border border-slate-200/90 shadow-sm overflow-hidden flex flex-col h-[480px]">
          {/* Chat Header */}
          <div className="p-4 bg-slate-900 text-white flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="relative">
                <img
                  src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=100&auto=format&fit=crop&q=80"
                  alt="Elena Vance"
                  className="w-10 h-10 rounded-full object-cover ring-2 ring-emerald-400"
                />
                <span className="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-500 ring-2 ring-slate-900"></span>
              </div>
              <div>
                <h4 className="text-xs font-bold">Elena Vance</h4>
                <p className="text-[10px] text-slate-300">Dedicated VIP Concierge</p>
              </div>
            </div>
            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
              Active
            </span>
          </div>

          {/* Messages Scroll Area */}
          <div className="flex-1 p-4 overflow-y-auto space-y-3 bg-slate-50/50">
            {messages.map((msg) => {
              const isUser = msg.sender === 'user';
              return (
                <div
                  key={msg.id}
                  className={`flex flex-col ${isUser ? 'items-end' : 'items-start'}`}
                >
                  <span className="text-[10px] text-slate-400 px-1 mb-1">
                    {msg.name} • {msg.time}
                  </span>
                  <div
                    className={`max-w-[80%] rounded-2xl p-3 text-xs leading-relaxed shadow-xs ${
                      isUser
                        ? 'bg-slate-900 text-white rounded-br-xs'
                        : 'bg-white text-slate-800 border border-slate-200/80 rounded-bl-xs'
                    }`}
                  >
                    {msg.text}
                  </div>
                </div>
              );
            })}
            {isTyping && (
              <div className="flex items-center gap-1.5 p-3 rounded-2xl bg-white border border-slate-200 w-20 text-slate-400">
                <div className="w-1.5 h-1.5 rounded-full bg-slate-400 animate-bounce"></div>
                <div className="w-1.5 h-1.5 rounded-full bg-slate-400 animate-bounce [animation-delay:0.2s]"></div>
                <div className="w-1.5 h-1.5 rounded-full bg-slate-400 animate-bounce [animation-delay:0.4s]"></div>
              </div>
            )}
          </div>

          {/* Chat Input */}
          <form onSubmit={handleSendMessage} className="p-3 bg-white border-t border-slate-100 flex gap-2">
            <input
              type="text"
              placeholder="Ask about orders, returns, or rewards..."
              value={inputMessage}
              onChange={(e) => setInputMessage(e.target.value)}
              className="flex-1 text-xs px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
            />
            <button
              type="submit"
              className="p-2.5 rounded-xl bg-slate-900 hover:bg-black text-white transition-colors"
            >
              <Send className="w-4 h-4" />
            </button>
          </form>
        </div>

        {/* Active Support Tickets */}
        <div className="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-sm flex flex-col justify-between space-y-4">
          <div>
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <h3 className="text-base font-bold text-slate-900">Your Support Tickets</h3>
              <span className="text-xs font-semibold text-slate-500">
                {supportTickets.length} Inquiries
              </span>
            </div>

            <div className="divide-y divide-slate-100 mt-2">
              {supportTickets.map((ticket) => (
                <div key={ticket.id} className="py-3.5 space-y-2 first:pt-0 last:pb-0">
                  <div className="flex items-center justify-between">
                    <span className="font-mono text-xs font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
                      #{ticket.ticketNumber}
                    </span>
                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full capitalize ${
                      ticket.status === 'resolved' ? 'bg-emerald-100 text-emerald-800' :
                      ticket.status === 'in_progress' ? 'bg-sky-100 text-sky-800' : 'bg-amber-100 text-amber-800'
                    }`}>
                      {ticket.status.replace('_', ' ')}
                    </span>
                  </div>

                  <div>
                    <h4 className="text-xs font-bold text-slate-900">{ticket.subject}</h4>
                    <p className="text-[11px] text-slate-500 mt-0.5 line-clamp-1">{ticket.lastMessage}</p>
                  </div>

                  <div className="flex items-center justify-between text-[10px] text-slate-400">
                    <span>Assigned to: {ticket.assignedAgent}</span>
                    <span>Updated {ticket.updatedAt}</span>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="pt-3 border-t border-slate-100">
            <button
              onClick={() => setIsNewTicketOpen(true)}
              className="w-full py-2.5 rounded-xl border border-dashed border-slate-300 hover:border-slate-400 text-slate-700 text-xs font-bold flex items-center justify-center gap-1.5 transition-colors"
            >
              <Plus className="w-4 h-4" />
              <span>Create Another Ticket</span>
            </button>
          </div>
        </div>

      </div>

      {/* FAQ Accordion Section */}
      <div className="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/90 shadow-sm space-y-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <span className="text-[11px] font-bold text-indigo-600 uppercase tracking-wider">
              Self-Serve Assistance
            </span>
            <h3 className="text-lg font-bold text-slate-900 mt-0.5">
              Frequently Asked Questions
            </h3>
          </div>

          {/* Category Tabs */}
          <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-xl overflow-x-auto">
            {['All', 'Orders', 'Returns', 'Shipping', 'Rewards'].map((cat) => (
              <button
                key={cat}
                onClick={() => setSelectedFaqCategory(cat as any)}
                className={`px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors ${
                  selectedFaqCategory === cat ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-800'
                }`}
              >
                {cat}
              </button>
            ))}
          </div>
        </div>

        {/* FAQ Accordion List */}
        <div className="space-y-2">
          {filteredFaqs.map((faq, index) => {
            const isOpen = openFaqIndex === index;
            return (
              <div
                key={index}
                className="border border-slate-200/80 rounded-2xl overflow-hidden transition-colors"
              >
                <button
                  type="button"
                  onClick={() => setOpenFaqIndex(isOpen ? null : index)}
                  className="w-full p-4 text-left flex items-center justify-between gap-3 hover:bg-slate-50 transition-colors"
                >
                  <span className="text-xs sm:text-sm font-bold text-slate-900">{faq.question}</span>
                  {isOpen ? <ChevronUp className="w-4 h-4 text-slate-400" /> : <ChevronDown className="w-4 h-4 text-slate-400" />}
                </button>
                {isOpen && (
                  <div className="px-4 pb-4 pt-1 text-xs text-slate-600 leading-relaxed border-t border-slate-100 bg-slate-50/40">
                    {faq.answer}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </div>

      {/* Open Ticket Modal */}
      {isNewTicketOpen && (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <div className="flex items-center gap-2">
                <HelpCircle className="w-4 h-4 text-indigo-600" />
                <h3 className="text-base font-bold text-slate-900">Create Support Ticket</h3>
              </div>
              <button
                onClick={() => setIsNewTicketOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-700"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleCreateTicketSubmit} className="p-6 space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Inquiry Category</label>
                <select
                  value={ticketCategory}
                  onChange={(e) => setTicketCategory(e.target.value as any)}
                  className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                >
                  <option value="order_issue">Order Tracking / Status</option>
                  <option value="return_inquiry">Return / Exchange Request</option>
                  <option value="product_question">Product / Sizing Advice</option>
                  <option value="billing">Billing & Gift Cards</option>
                  <option value="other">General Inquiries</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Subject</label>
                <input
                  type="text"
                  placeholder="e.g. Inquire about carrier delivery delay"
                  value={ticketSubject}
                  onChange={(e) => setTicketSubject(e.target.value)}
                  className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Detailed Message</label>
                <textarea
                  rows={4}
                  placeholder="Describe what happened or how our concierge team can help..."
                  value={ticketMessage}
                  onChange={(e) => setTicketMessage(e.target.value)}
                  className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                  required
                />
              </div>

              <div className="flex gap-2 justify-end pt-2 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setIsNewTicketOpen(false)}
                  className="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold"
                >
                  Submit Ticket
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
};
