import React, { useState } from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  X, 
  Package, 
  Truck, 
  MapPin, 
  Calendar, 
  CreditCard, 
  Download, 
  RotateCcw, 
  Star, 
  CheckCircle2, 
  Clock, 
  ExternalLink,
  Copy,
  Check,
  Headphones,
  ShoppingBag
} from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';
import { OrderItem } from '../types/dashboard';

export const OrderDetailsModal: React.FC<{
  onOpenReturn: (orderId: string, item: OrderItem) => void;
  onOpenReview: (orderId: string, item: OrderItem) => void;
}> = ({ onOpenReturn, onOpenReview }) => {
  const {
    selectedOrder,
    setSelectedOrder,
    formatPrice,
    reorderItems,
    setActiveTab,
    createSupportTicket,
    showToast
  } = useDashboard();

  const [copiedTracking, setCopiedTracking] = useState(false);
  const [showInvoicePrint, setShowInvoicePrint] = useState(false);

  if (!selectedOrder) return null;

  const handleCopyTracking = () => {
    navigator.clipboard.writeText(selectedOrder.trackingNumber);
    setCopiedTracking(true);
    showToast('Tracking Number Copied', selectedOrder.trackingNumber);
    setTimeout(() => setCopiedTracking(false), 2000);
  };

  const handleNeedHelp = () => {
    const orderNum = selectedOrder.orderNumber;
    setSelectedOrder(null);
    createSupportTicket(`Order Inquiry: ${orderNum}`, 'Shipping & Delivery', `I have a question regarding my order #${orderNum}.`, orderNum);
    setActiveTab('support');
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-6">
      <motion.div
        initial={{ opacity: 0, scale: 0.96, y: 15 }}
        animate={{ opacity: 1, scale: 1, y: 0 }}
        exit={{ opacity: 0, scale: 0.96 }}
        className="bg-white rounded-3xl shadow-2xl max-w-3xl w-full overflow-hidden border border-slate-200 flex flex-col max-h-[90vh]"
      >
        {/* Top Header */}
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
          <div>
            <div className="flex items-center gap-2">
              <span className="text-xs font-bold font-mono text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100">
                {selectedOrder.orderNumber}
              </span>
              <span className={`text-xs font-semibold px-2 py-0.5 rounded-full capitalize ${
                selectedOrder.status === 'delivered' ? 'bg-emerald-100 text-emerald-800' :
                selectedOrder.status === 'out_for_delivery' ? 'bg-sky-100 text-sky-800' :
                selectedOrder.status === 'shipped' ? 'bg-indigo-100 text-indigo-800' :
                selectedOrder.status === 'returned' ? 'bg-purple-100 text-purple-800' :
                selectedOrder.status === 'cancelled' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'
              }`}>
                {selectedOrder.status.replace('_', ' ')}
              </span>
            </div>
            <p className="text-xs text-slate-500 mt-1">
              Placed on {selectedOrder.date} • {selectedOrder.items.length} item(s)
            </p>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={() => setShowInvoicePrint(!showInvoicePrint)}
              className="p-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-colors"
              title="Print Receipt"
            >
              <Download className="w-4 h-4" />
              <span className="hidden sm:inline">Receipt</span>
            </button>
            <button
              onClick={() => setSelectedOrder(null)}
              className="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-xl transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Scrollable Modal Body */}
        <div className="flex-1 overflow-y-auto p-6 space-y-6">
          
          {/* Tracking Status Card */}
          <div className="p-5 rounded-2xl bg-gradient-to-br from-slate-900 to-indigo-950 text-white shadow-md relative overflow-hidden">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-white/10">
              <div>
                <span className="text-[11px] font-semibold tracking-wider text-indigo-300 uppercase">
                  Live Courier Tracking
                </span>
                <h4 className="text-base font-bold text-white flex items-center gap-2 mt-0.5">
                  <Truck className="w-4 h-4 text-sky-400" />
                  {selectedOrder.carrier}
                </h4>
              </div>

              <div className="flex items-center gap-2 bg-white/10 px-3 py-1.5 rounded-xl backdrop-blur-xs text-xs font-mono">
                <span className="text-slate-300 truncate max-w-[160px] sm:max-w-none">
                  {selectedOrder.trackingNumber}
                </span>
                <button
                  onClick={handleCopyTracking}
                  className="text-slate-300 hover:text-white transition-colors"
                  title="Copy Tracking Number"
                >
                  {copiedTracking ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
                </button>
              </div>
            </div>

            <div className="pt-4 flex items-center justify-between text-xs">
              <div>
                <p className="text-slate-400 text-[11px]">Estimated Delivery</p>
                <p className="text-sm font-bold text-white mt-0.5">{selectedOrder.estimatedDelivery}</p>
              </div>
              <div className="text-right">
                <p className="text-slate-400 text-[11px]">Destination</p>
                <p className="text-xs font-semibold text-white mt-0.5">
                  {selectedOrder.shippingAddress.city}, {selectedOrder.shippingAddress.state}
                </p>
              </div>
            </div>

            {/* Tracking Milestones */}
            <div className="mt-5 pt-4 border-t border-white/10 space-y-4">
              {selectedOrder.trackingTimeline.map((step, idx) => (
                <div key={idx} className="flex items-start gap-3 relative">
                  {idx < selectedOrder.trackingTimeline.length - 1 && (
                    <div className={`absolute left-2.5 top-6 bottom-0 w-0.5 -mb-4 ${
                      step.completed ? 'bg-indigo-400' : 'bg-slate-700'
                    }`} />
                  )}
                  <div className={`w-5 h-5 rounded-full flex items-center justify-center shrink-0 z-10 ${
                    step.current ? 'bg-sky-400 text-slate-900 ring-4 ring-sky-400/20' :
                    step.completed ? 'bg-indigo-400 text-slate-900' : 'bg-slate-800 text-slate-500'
                  }`}>
                    {step.completed ? <Check className="w-3 h-3 stroke-[3]" /> : <div className="w-1.5 h-1.5 rounded-full bg-slate-500" />}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-baseline justify-between gap-2">
                      <p className={`text-xs font-bold ${step.current ? 'text-sky-300' : step.completed ? 'text-white' : 'text-slate-400'}`}>
                        {step.title}
                      </p>
                      <span className="text-[10px] text-slate-400 shrink-0">{step.timestamp}</span>
                    </div>
                    <p className="text-[11px] text-slate-300 mt-0.5 leading-snug">
                      {step.description}
                    </p>
                    {step.location && (
                      <p className="text-[10px] text-slate-400 mt-0.5 flex items-center gap-1">
                        <MapPin className="w-2.5 h-2.5" />
                        {step.location}
                      </p>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Items Breakdown */}
          <div>
            <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3">
              Items in this Order ({selectedOrder.items.length})
            </h4>

            <div className="space-y-3">
              {selectedOrder.items.map((item) => (
                <div
                  key={item.id}
                  className="p-3.5 rounded-2xl border border-slate-200 bg-white hover:border-slate-300 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                >
                  <div className="flex items-center gap-3.5 min-w-0">
                    <img
                      src={item.image}
                      alt={item.title}
                      className="w-16 h-16 rounded-xl object-cover border border-slate-100 shrink-0"
                    />
                    <div className="min-w-0">
                      <p className="text-[11px] font-semibold text-indigo-600 uppercase tracking-wider">
                        {item.brand}
                      </p>
                      <h5 className="text-xs font-bold text-slate-900 truncate">{item.title}</h5>
                      <p className="text-[11px] text-slate-500 mt-0.5">
                        Qty: {item.quantity} {item.variant ? `• ${item.variant}` : ''}
                      </p>
                      <p className="text-xs font-bold text-slate-900 mt-1">
                        {formatPrice(item.price)}
                      </p>
                    </div>
                  </div>

                  {/* Actions for Item */}
                  <div className="flex items-center gap-2 self-end sm:self-center shrink-0">
                    {selectedOrder.status === 'delivered' && (
                      <>
                        <button
                          onClick={() => onOpenReview(selectedOrder.id, item)}
                          className="px-3 py-1.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-semibold flex items-center gap-1.5 transition-colors"
                        >
                          <Star className="w-3.5 h-3.5 text-amber-500 fill-amber-400" />
                          <span>{item.userRating ? `Reviewed (${item.userRating}★)` : 'Review & Earn Points'}</span>
                        </button>

                        <button
                          onClick={() => onOpenReturn(selectedOrder.id, item)}
                          className="px-3 py-1.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-semibold flex items-center gap-1.5 transition-colors"
                        >
                          <RotateCcw className="w-3.5 h-3.5 text-slate-500" />
                          <span>Return / Exchange</span>
                        </button>
                      </>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Shipping & Payment Summaries */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Delivery Address */}
            <div className="p-4 rounded-2xl bg-slate-50 border border-slate-200">
              <h5 className="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                <MapPin className="w-3.5 h-3.5 text-indigo-600" />
                Delivery Address
              </h5>
              <p className="text-xs font-bold text-slate-900">{selectedOrder.shippingAddress.recipientName}</p>
              <p className="text-xs text-slate-600 mt-0.5">{selectedOrder.shippingAddress.street}, {selectedOrder.shippingAddress.apartment}</p>
              <p className="text-xs text-slate-600">{selectedOrder.shippingAddress.city}, {selectedOrder.shippingAddress.state} {selectedOrder.shippingAddress.zipCode}</p>
              {selectedOrder.shippingAddress.deliveryInstructions && (
                <p className="text-[11px] text-slate-500 mt-2 italic bg-white p-2 rounded-lg border border-slate-200">
                  Note: "{selectedOrder.shippingAddress.deliveryInstructions}"
                </p>
              )}
            </div>

            {/* Payment & Receipt Summary */}
            <div className="p-4 rounded-2xl bg-slate-50 border border-slate-200">
              <h5 className="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                <CreditCard className="w-3.5 h-3.5 text-indigo-600" />
                Payment & Billing
              </h5>
              <div className="space-y-1 text-xs text-slate-600">
                <div className="flex justify-between">
                  <span>Subtotal</span>
                  <span className="font-semibold text-slate-800">{formatPrice(selectedOrder.subtotal)}</span>
                </div>
                {selectedOrder.discount > 0 && (
                  <div className="flex justify-between text-emerald-600">
                    <span>Discount Applied</span>
                    <span>-{formatPrice(selectedOrder.discount)}</span>
                  </div>
                )}
                <div className="flex justify-between">
                  <span>Shipping</span>
                  <span className="font-semibold text-emerald-600">
                    {selectedOrder.shippingFee === 0 ? 'FREE' : formatPrice(selectedOrder.shippingFee)}
                  </span>
                </div>
                <div className="flex justify-between">
                  <span>Estimated Tax</span>
                  <span>{formatPrice(selectedOrder.tax)}</span>
                </div>
                <div className="flex justify-between font-bold text-slate-900 pt-1.5 border-t border-slate-200 text-sm">
                  <span>Total Paid</span>
                  <span className="text-indigo-950 font-display font-bold">
                    {formatPrice(selectedOrder.total)}
                  </span>
                </div>
              </div>
            </div>
          </div>

        </div>

        {/* Modal Footer Controls */}
        <div className="px-6 py-4 bg-slate-50 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
          <button
            onClick={handleNeedHelp}
            className="text-xs font-semibold text-slate-600 hover:text-indigo-600 flex items-center gap-1.5 transition-colors"
          >
            <Headphones className="w-4 h-4 text-indigo-600" />
            <span>Need Help with Order?</span>
          </button>

          <div className="flex items-center gap-2">
            <button
              onClick={() => {
                reorderItems(selectedOrder);
                setSelectedOrder(null);
              }}
              className="px-4 py-2 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold flex items-center gap-1.5 shadow-sm transition-all"
            >
              <ShoppingBag className="w-3.5 h-3.5" />
              <span>Buy These Again</span>
            </button>
            <button
              onClick={() => setSelectedOrder(null)}
              className="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors"
            >
              Close
            </button>
          </div>
        </div>
      </motion.div>
    </div>
  );
};
