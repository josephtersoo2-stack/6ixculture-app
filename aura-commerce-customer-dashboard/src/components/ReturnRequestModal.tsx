import React, { useState } from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  X, 
  RotateCcw, 
  Check, 
  Truck, 
  QrCode, 
  ArrowRight, 
  ShieldAlert, 
  FileText, 
  DollarSign, 
  Sparkles 
} from 'lucide-react';
import { motion } from 'motion/react';
import { OrderItem } from '../types/dashboard';

interface ReturnRequestModalProps {
  isOpen: boolean;
  orderId: string | null;
  item: OrderItem | null;
  onClose: () => void;
}

export const ReturnRequestModal: React.FC<ReturnRequestModalProps> = ({
  isOpen,
  orderId,
  item,
  onClose
}) => {
  const { requestReturn, formatPrice } = useDashboard();

  const [step, setStep] = useState<1 | 2 | 3>(1);
  const [reason, setReason] = useState('Fit / Sizing was inaccurate');
  const [returnType, setReturnType] = useState<'store_credit' | 'original_payment'>('store_credit');
  const [method, setMethod] = useState<'drop_off' | 'courier_pickup'>('drop_off');
  const [additionalNotes, setAdditionalNotes] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (!isOpen || !item || !orderId) return null;

  const returnReasons = [
    'Fit / Sizing was inaccurate',
    'Item does not match photo / description',
    'Arrived too late for intended date',
    'Changed mind / No longer needed',
    'Item defective or damaged in box',
    'Received incorrect product'
  ];

  const handleConfirmReturn = () => {
    setIsSubmitting(true);
    setTimeout(() => {
      requestReturn(orderId, item.id, reason, returnType);
      setIsSubmitting(false);
      setStep(3); // Show label step
    }, 1000);
  };

  const handleFinish = () => {
    setStep(1);
    onClose();
  };

  const refundEstimate = item.price * item.quantity;
  const storeCreditBonus = refundEstimate * 0.05; // 5% bonus for store credit!

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-6">
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        exit={{ opacity: 0, scale: 0.95 }}
        className="bg-white rounded-3xl shadow-2xl max-w-xl w-full overflow-hidden border border-slate-200"
      >
        {/* Modal Header */}
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
              <RotateCcw className="w-4 h-4" />
            </div>
            <div>
              <h3 className="text-base font-bold text-slate-900">
                {step === 3 ? 'Return Authorized' : 'Request Return or Exchange'}
              </h3>
              <p className="text-xs text-slate-500">Hassle-free 30-day return with instant prepaid label</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Modal Content */}
        <div className="p-6 space-y-6">
          
          {/* Target Item Pill */}
          <div className="p-3 bg-slate-50 rounded-2xl border border-slate-200 flex items-center gap-3">
            <img src={item.image} alt={item.title} className="w-12 h-12 rounded-xl object-cover" />
            <div className="flex-1 min-w-0">
              <p className="text-xs font-bold text-slate-900 truncate">{item.title}</p>
              <p className="text-[11px] text-slate-500">{item.brand} • Qty: {item.quantity}</p>
            </div>
            <span className="text-xs font-bold text-slate-900 shrink-0">
              {formatPrice(item.price * item.quantity)}
            </span>
          </div>

          {step === 1 && (
            <div className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">
                  1. Reason for Return
                </label>
                <div className="grid grid-cols-1 gap-2">
                  {returnReasons.map((r) => (
                    <button
                      key={r}
                      type="button"
                      onClick={() => setReason(r)}
                      className={`text-left p-3 rounded-xl border text-xs font-medium transition-all flex items-center justify-between ${
                        reason === r
                          ? 'border-indigo-600 bg-indigo-50/50 text-indigo-950 font-semibold'
                          : 'border-slate-200 hover:border-slate-300 text-slate-700'
                      }`}
                    >
                      <span>{r}</span>
                      {reason === r && <Check className="w-4 h-4 text-indigo-600" />}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Additional Details (Optional)
                </label>
                <textarea
                  rows={2}
                  value={additionalNotes}
                  onChange={(e) => setAdditionalNotes(e.target.value)}
                  placeholder="Provide any details that will help our quality team..."
                  className="w-full text-xs p-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div className="pt-2">
                <button
                  type="button"
                  onClick={() => setStep(2)}
                  className="w-full py-2.5 px-4 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold flex items-center justify-center gap-2 transition-colors"
                >
                  <span>Continue to Refund Method</span>
                  <ArrowRight className="w-4 h-4" />
                </button>
              </div>
            </div>
          )}

          {step === 2 && (
            <div className="space-y-5">
              <div>
                <label className="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">
                  2. Choose Refund Preference
                </label>
                
                <div className="space-y-2.5">
                  <div
                    onClick={() => setReturnType('store_credit')}
                    className={`p-3.5 rounded-2xl border-2 cursor-pointer transition-all ${
                      returnType === 'store_credit'
                        ? 'border-indigo-600 bg-indigo-50/50'
                        : 'border-slate-200 hover:border-slate-300 bg-white'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <Sparkles className="w-4 h-4 text-indigo-600" />
                        <span className="text-xs font-bold text-slate-900">
                          Instant Aura Store Credit + 5% Bonus
                        </span>
                      </div>
                      <span className="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-md">
                        Fastest & Recommended
                      </span>
                    </div>
                    <p className="text-xs text-slate-600 mt-1">
                      Available immediately in your Aura Wallet: <span className="font-bold text-indigo-950">{formatPrice(refundEstimate + storeCreditBonus)}</span>
                    </p>
                  </div>

                  <div
                    onClick={() => setReturnType('original_payment')}
                    className={`p-3.5 rounded-2xl border-2 cursor-pointer transition-all ${
                      returnType === 'original_payment'
                        ? 'border-indigo-600 bg-indigo-50/50'
                        : 'border-slate-200 hover:border-slate-300 bg-white'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <DollarSign className="w-4 h-4 text-slate-600" />
                        <span className="text-xs font-bold text-slate-900">
                          Original Payment Method
                        </span>
                      </div>
                      <span className="text-xs font-bold text-slate-900">
                        {formatPrice(refundEstimate)}
                      </span>
                    </div>
                    <p className="text-[11px] text-slate-500 mt-1">
                      Refunded to original card in 2-3 business days after drop-off.
                    </p>
                  </div>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">
                  3. Drop-off Method
                </label>
                <div className="grid grid-cols-2 gap-2.5">
                  <div
                    onClick={() => setMethod('drop_off')}
                    className={`p-3 rounded-xl border cursor-pointer text-center transition-all ${
                      method === 'drop_off'
                        ? 'border-indigo-600 bg-indigo-50/50 font-semibold text-indigo-950'
                        : 'border-slate-200 hover:border-slate-300 text-slate-700'
                    }`}
                  >
                    <QrCode className="w-5 h-5 mx-auto mb-1 text-indigo-600" />
                    <p className="text-xs font-bold">QR Code Drop-off</p>
                    <p className="text-[10px] text-slate-500">FedEx / UPS Store (No printer needed)</p>
                  </div>

                  <div
                    onClick={() => setMethod('courier_pickup')}
                    className={`p-3 rounded-xl border cursor-pointer text-center transition-all ${
                      method === 'courier_pickup'
                        ? 'border-indigo-600 bg-indigo-50/50 font-semibold text-indigo-950'
                        : 'border-slate-200 hover:border-slate-300 text-slate-700'
                    }`}
                  >
                    <Truck className="w-5 h-5 mx-auto mb-1 text-indigo-600" />
                    <p className="text-xs font-bold">Free Home Pickup</p>
                    <p className="text-[10px] text-slate-500">Driver collects package from doorstep</p>
                  </div>
                </div>
              </div>

              <div className="flex gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setStep(1)}
                  className="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                >
                  Back
                </button>
                <button
                  type="button"
                  onClick={handleConfirmReturn}
                  disabled={isSubmitting}
                  className="flex-1 py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold flex items-center justify-center gap-2 shadow-sm transition-all"
                >
                  {isSubmitting ? (
                    <div className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                  ) : (
                    <>
                      <span>Generate Return Shipping Label</span>
                      <Check className="w-4 h-4" />
                    </>
                  )}
                </button>
              </div>
            </div>
          )}

          {step === 3 && (
            <div className="text-center py-4 space-y-4">
              <div className="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto shadow-sm">
                <Check className="w-7 h-7 stroke-[3]" />
              </div>

              <div>
                <h4 className="text-base font-bold text-slate-900">
                  Return Label & QR Code Ready!
                </h4>
                <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                  Show this QR code at any FedEx or UPS drop-off location, or print the prepaid label below.
                </p>
              </div>

              {/* QR Box Visual */}
              <div className="bg-slate-50 p-4 rounded-2xl border border-slate-200 inline-block">
                <div className="w-36 h-36 bg-white border border-slate-300 rounded-xl p-2 flex flex-col items-center justify-center mx-auto">
                  <QrCode className="w-28 h-28 text-slate-900" />
                </div>
                <p className="text-[11px] font-mono text-slate-600 mt-2">
                  RET-AUTH-FX98201
                </p>
              </div>

              <div className="flex justify-center gap-3">
                <button
                  type="button"
                  onClick={handleFinish}
                  className="px-6 py-2.5 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold shadow-md transition-colors"
                >
                  Done & Back to Dashboard
                </button>
              </div>
            </div>
          )}

        </div>
      </motion.div>
    </div>
  );
};
