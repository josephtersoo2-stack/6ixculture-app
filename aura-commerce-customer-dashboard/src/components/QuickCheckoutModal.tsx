import React, { useState } from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  X, 
  Check, 
  MapPin, 
  CreditCard, 
  ShieldCheck, 
  Truck, 
  Lock, 
  Sparkles,
  ArrowRight,
  Plus
} from 'lucide-react';
import { motion } from 'motion/react';

export const QuickCheckoutModal: React.FC<{ isOpen: boolean; onClose: () => void }> = ({ isOpen, onClose }) => {
  const {
    cart,
    cartTotal,
    cartSubtotal,
    addresses,
    paymentCards,
    walletBalance,
    formatPrice,
    checkoutCart,
    user
  } = useDashboard();

  const [selectedAddressId, setSelectedAddressId] = useState<string>(
    addresses.find(a => a.isDefaultShipping)?.id || addresses[0]?.id || ''
  );
  const [paymentType, setPaymentType] = useState<string>('card');
  const [useStoreCredit, setUseStoreCredit] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);

  if (!isOpen) return null;

  const handleCompleteOrder = () => {
    setIsProcessing(true);
    setTimeout(() => {
      checkoutCart(selectedAddressId, paymentType);
      setIsProcessing(false);
      onClose();
    }, 1200);
  };

  const selectedAddr = addresses.find(a => a.id === selectedAddressId) || addresses[0];

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
      <motion.div
        initial={{ opacity: 0, scale: 0.95, y: 10 }}
        animate={{ opacity: 1, scale: 1, y: 0 }}
        exit={{ opacity: 0, scale: 0.95 }}
        className="bg-white rounded-3xl shadow-2xl max-w-2xl w-full overflow-hidden border border-slate-200"
      >
        {/* Header */}
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center">
              <Lock className="w-4 h-4" />
            </div>
            <div>
              <h3 className="text-base font-bold text-slate-900">Express VIP Checkout</h3>
              <p className="text-xs text-slate-500">Fast 1-click confirmation with saved preferences</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-6 space-y-6 max-h-[75vh] overflow-y-auto">
          {/* Shipping Address Selection */}
          <div>
            <div className="flex items-center justify-between mb-2.5">
              <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                <MapPin className="w-3.5 h-3.5 text-indigo-600" />
                Shipping Destination
              </h4>
              <span className="text-[11px] text-indigo-600 font-semibold">
                FedEx 2-Day Included
              </span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {addresses.map((addr) => {
                const isSelected = addr.id === selectedAddressId;
                return (
                  <div
                    key={addr.id}
                    onClick={() => setSelectedAddressId(addr.id)}
                    className={`p-3.5 rounded-2xl border-2 cursor-pointer transition-all ${
                      isSelected
                        ? 'border-indigo-600 bg-indigo-50/40 shadow-xs'
                        : 'border-slate-200 hover:border-slate-300 bg-white'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <span className="text-xs font-bold text-slate-900">{addr.label}</span>
                      {isSelected && (
                        <div className="w-4 h-4 rounded-full bg-indigo-600 text-white flex items-center justify-center">
                          <Check className="w-2.5 h-2.5 stroke-[3]" />
                        </div>
                      )}
                    </div>
                    <p className="text-xs text-slate-700 font-medium mt-1">{addr.recipientName}</p>
                    <p className="text-[11px] text-slate-500 line-clamp-1">{addr.street}, {addr.apartment}</p>
                    <p className="text-[11px] text-slate-500">{addr.city}, {addr.state} {addr.zipCode}</p>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Payment Method Selection */}
          <div>
            <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
              <CreditCard className="w-3.5 h-3.5 text-indigo-600" />
              Payment Method
            </h4>

            <div className="space-y-2.5">
              {paymentCards.map((card) => {
                const isSelected = paymentType === 'card' && card.isDefault;
                return (
                  <div
                    key={card.id}
                    onClick={() => setPaymentType('card')}
                    className={`p-3 rounded-2xl border-2 cursor-pointer transition-all flex items-center justify-between ${
                      isSelected
                        ? 'border-indigo-600 bg-indigo-50/40 shadow-xs'
                        : 'border-slate-200 hover:border-slate-300 bg-white'
                    }`}
                  >
                    <div className="flex items-center gap-3">
                      <div className="w-9 h-6 rounded bg-slate-900 text-white text-[9px] font-bold flex items-center justify-center uppercase tracking-wider">
                        {card.cardType}
                      </div>
                      <div>
                        <p className="text-xs font-bold text-slate-900">
                          •••• •••• •••• {card.last4}
                        </p>
                        <p className="text-[10px] text-slate-500">
                          Expires {card.expMonth}/{card.expYear} • {card.cardholderName}
                        </p>
                      </div>
                    </div>
                    {isSelected && (
                      <div className="w-4 h-4 rounded-full bg-indigo-600 text-white flex items-center justify-center">
                        <Check className="w-2.5 h-2.5 stroke-[3]" />
                      </div>
                    )}
                  </div>
                );
              })}

              {/* Apple Pay / Store Credit Quick Options */}
              <div
                onClick={() => setPaymentType('apple_pay')}
                className={`p-3 rounded-2xl border-2 cursor-pointer transition-all flex items-center justify-between ${
                  paymentType === 'apple_pay'
                    ? 'border-indigo-600 bg-indigo-50/40 shadow-xs'
                    : 'border-slate-200 hover:border-slate-300 bg-white'
                }`}
              >
                <div className="flex items-center gap-3">
                  <div className="w-9 h-6 rounded bg-black text-white text-[10px] font-bold flex items-center justify-center">
                     Pay
                  </div>
                  <div>
                    <p className="text-xs font-bold text-slate-900">Apple Pay Express</p>
                    <p className="text-[10px] text-slate-500">Biometric Touch / Face ID Verification</p>
                  </div>
                </div>
                {paymentType === 'apple_pay' && (
                  <div className="w-4 h-4 rounded-full bg-indigo-600 text-white flex items-center justify-center">
                    <Check className="w-2.5 h-2.5 stroke-[3]" />
                  </div>
                )}
              </div>
            </div>

            {/* Store Credit Balance Offset Toggle */}
            {walletBalance > 0 && (
              <div className="mt-3 p-3 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Sparkles className="w-4 h-4 text-emerald-600" />
                  <div>
                    <p className="text-xs font-bold text-emerald-950">
                      Apply Store Credit ({formatPrice(walletBalance)})
                    </p>
                    <p className="text-[10px] text-emerald-700">Offset your order balance automatically</p>
                  </div>
                </div>
                <input
                  type="checkbox"
                  checked={useStoreCredit}
                  onChange={(e) => setUseStoreCredit(e.target.checked)}
                  className="w-4 h-4 text-emerald-600 rounded border-emerald-300 focus:ring-emerald-500"
                />
              </div>
            )}
          </div>

          {/* Items Preview */}
          <div>
            <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">
              Order Items ({cart.length})
            </h4>
            <div className="divide-y divide-slate-100 bg-slate-50/70 p-3 rounded-2xl border border-slate-100">
              {cart.map((item) => (
                <div key={item.id} className="py-2 first:pt-0 last:pb-0 flex items-center justify-between text-xs">
                  <div className="flex items-center gap-2.5 min-w-0">
                    <img src={item.image} alt={item.title} className="w-9 h-9 rounded-lg object-cover" />
                    <div className="min-w-0">
                      <p className="font-semibold text-slate-900 truncate">{item.title}</p>
                      <p className="text-[11px] text-slate-500">Qty: {item.quantity} {item.size ? `• ${item.size}` : ''}</p>
                    </div>
                  </div>
                  <span className="font-bold text-slate-800 shrink-0">
                    {formatPrice(item.price * item.quantity)}
                  </span>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Modal Bottom Confirm Bar */}
        <div className="px-6 py-4 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
          <div>
            <p className="text-xs text-slate-500">Total Charged</p>
            <p className="text-xl font-display font-bold text-indigo-950">
              {formatPrice(useStoreCredit ? Math.max(0, cartTotal - walletBalance) : cartTotal)}
            </p>
          </div>

          <div className="flex items-center gap-2 w-full sm:w-auto">
            <button
              onClick={onClose}
              disabled={isProcessing}
              className="w-full sm:w-auto px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors"
            >
              Cancel
            </button>
            <button
              onClick={handleCompleteOrder}
              disabled={isProcessing}
              className="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold flex items-center justify-center gap-2 shadow-lg transition-all"
            >
              {isProcessing ? (
                <>
                  <div className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                  <span>Processing VIP Order...</span>
                </>
              ) : (
                <>
                  <span>Place VIP Order</span>
                  <ArrowRight className="w-3.5 h-3.5" />
                </>
              )}
            </button>
          </div>
        </div>
      </motion.div>
    </div>
  );
};
