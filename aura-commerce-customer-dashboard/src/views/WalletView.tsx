import React, { useState } from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  CreditCard, 
  Plus, 
  Trash2, 
  Sparkles, 
  Gift, 
  ArrowDownLeft, 
  ArrowUpRight, 
  ShieldCheck, 
  Lock, 
  Check,
  X,
  Wallet
} from 'lucide-react';
import { PaymentCard } from '../types/dashboard';

export const WalletView: React.FC = () => {
  const {
    paymentCards,
    addPaymentCard,
    deletePaymentCard,
    setDefaultPaymentCard,
    walletBalance,
    creditTransactions,
    redeemGiftCard,
    formatPrice,
    showToast
  } = useDashboard();

  const [isAddCardOpen, setIsAddCardOpen] = useState(false);
  const [giftCardCode, setGiftCardCode] = useState('');
  
  // Card Form
  const [cardholderName, setCardholderName] = useState('ALEXANDER HAYES');
  const [cardNumber, setCardNumber] = useState('');
  const [expMonth, setExpMonth] = useState('09');
  const [expYear, setExpYear] = useState('28');
  const [cvv, setCvv] = useState('');
  const [cardType, setCardType] = useState<'visa' | 'mastercard' | 'amex'>('visa');
  const [isDefault, setIsDefault] = useState(false);

  const handleRedeemCode = (e: React.FormEvent) => {
    e.preventDefault();
    if (!giftCardCode.trim()) return;
    if (redeemGiftCard(giftCardCode.trim(), 75.00)) {
      setGiftCardCode('');
    }
  };

  const handleAddCardSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const cleanNum = cardNumber.replace(/\s+/g, '');
    const last4 = cleanNum.slice(-4) || '7789';

    addPaymentCard({
      cardType,
      cardholderName: cardholderName.toUpperCase(),
      last4,
      expMonth,
      expYear,
      isDefault
    });

    setIsAddCardOpen(false);
    setCardNumber('');
    setCvv('');
  };

  return (
    <div className="space-y-6 max-w-7xl mx-auto pb-12">
      
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-display font-bold text-slate-900 tracking-tight">
            Wallet & Payment Methods
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-0.5">
            Manage saved payment cards, gift cards, and store credits.
          </p>
        </div>

        <button
          onClick={() => setIsAddCardOpen(true)}
          className="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold flex items-center gap-2 shadow-xs transition-colors self-start sm:self-auto"
        >
          <Plus className="w-4 h-4" />
          <span>Add Payment Method</span>
        </button>
      </div>

      {/* Store Credit & Gift Card Redeem Banner */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
        
        {/* Balance Card */}
        <div className="p-6 rounded-3xl bg-gradient-to-br from-emerald-950 via-teal-900 to-slate-950 text-white shadow-lg shadow-emerald-950/20 relative overflow-hidden flex flex-col justify-between">
          <div className="absolute top-0 right-0 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>
          <div>
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-bold uppercase tracking-wider text-emerald-300 flex items-center gap-1.5">
                <Wallet className="w-3.5 h-3.5" />
                Aura Store Credit
              </span>
              <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                Available to Spend
              </span>
            </div>
            <p className="text-3xl font-display font-bold text-white mt-3">
              {formatPrice(walletBalance)}
            </p>
            <p className="text-xs text-emerald-200 mt-1">
              Automatically applies as a 1-click discount during checkout.
            </p>
          </div>

          <div className="mt-4 pt-3 border-t border-white/10 text-[11px] text-slate-300 flex items-center gap-1">
            <Sparkles className="w-3.5 h-3.5 text-amber-300" />
            <span>Includes 5% return bonus credits</span>
          </div>
        </div>

        {/* Gift Card Redeem Form */}
        <div className="md:col-span-2 p-6 rounded-3xl bg-white border border-slate-200/90 shadow-sm flex flex-col justify-between space-y-4">
          <div>
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                  <Gift className="w-4 h-4" />
                </div>
                <h3 className="text-sm font-bold text-slate-900">Redeem Gift Card or Promo Code</h3>
              </div>
              <button
                type="button"
                onClick={() => setGiftCardCode('GC-AURA-VIP100')}
                className="text-[11px] font-semibold text-indigo-600 hover:underline"
              >
                Insert Test Code
              </button>
            </div>
            <p className="text-xs text-slate-500 mt-1">
              Enter your 16-character alphanumeric gift certificate to credit your wallet instantly.
            </p>
          </div>

          <form onSubmit={handleRedeemCode} className="flex gap-2">
            <input
              type="text"
              placeholder="e.g. GC-AURA-8921-994"
              value={giftCardCode}
              onChange={(e) => setGiftCardCode(e.target.value)}
              className="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono uppercase text-slate-800 placeholder-slate-400 focus:outline-none focus:border-indigo-500"
            />
            <button
              type="submit"
              className="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-xs transition-colors"
            >
              Redeem Code
            </button>
          </form>
        </div>

      </div>

      {/* Visual Payment Cards Shelf */}
      <div className="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-sm space-y-4">
        <h3 className="text-base font-bold text-slate-900">Saved Credit & Debit Cards</h3>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
          {paymentCards.map((card) => (
            <div
              key={card.id}
              className={`rounded-3xl p-5 bg-gradient-to-br ${card.bgColor || 'from-slate-900 to-indigo-950'} text-white shadow-md flex flex-col justify-between aspect-16/10 relative overflow-hidden border border-white/10`}
            >
              <div className="flex items-start justify-between">
                <div>
                  <span className="text-[10px] uppercase font-bold tracking-widest text-slate-400">
                    Aura Priority Card
                  </span>
                  <div className="w-8 h-6 rounded bg-amber-200/40 border border-amber-300/40 mt-2 flex items-center justify-center">
                    <div className="w-5 h-3 border border-amber-400/40 rounded-xs" />
                  </div>
                </div>

                <span className="text-xs font-bold uppercase tracking-wider bg-white/10 px-2 py-0.5 rounded backdrop-blur-xs">
                  {card.cardType}
                </span>
              </div>

              <div className="space-y-1">
                <p className="font-mono text-base tracking-widest text-slate-200">
                  •••• •••• •••• {card.last4}
                </p>
                <div className="flex items-center justify-between text-[10px] text-slate-400 uppercase pt-1">
                  <div>
                    <p className="text-[8px] text-slate-500">Cardholder</p>
                    <p className="font-bold text-white tracking-wide">{card.cardholderName}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-[8px] text-slate-500">Expires</p>
                    <p className="font-bold text-white">{card.expMonth}/{card.expYear}</p>
                  </div>
                </div>
              </div>

              {/* Bottom Card Hover Controls */}
              <div className="mt-2 pt-2 border-t border-white/10 flex items-center justify-between">
                {card.isDefault ? (
                  <span className="text-[10px] font-bold text-emerald-400 flex items-center gap-1">
                    <Check className="w-3 h-3" /> Default Card
                  </span>
                ) : (
                  <button
                    onClick={() => setDefaultPaymentCard(card.id)}
                    className="text-[10px] text-slate-300 hover:text-white underline font-semibold"
                  >
                    Set as Default
                  </button>
                )}

                {paymentCards.length > 1 && (
                  <button
                    onClick={() => deletePaymentCard(card.id)}
                    className="text-slate-400 hover:text-rose-400 p-1 transition-colors"
                    title="Remove Card"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Credit & Refund Transaction Ledger */}
      <div className="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-sm space-y-4">
        <h3 className="text-base font-bold text-slate-900">Wallet Activity & Refunds</h3>

        <div className="divide-y divide-slate-100">
          {creditTransactions.map((tx) => {
            const isPositive = tx.amount > 0;
            return (
              <div key={tx.id} className="py-3.5 flex items-center justify-between gap-3 first:pt-0 last:pb-0">
                <div className="flex items-center gap-3">
                  <div className={`w-9 h-9 rounded-xl flex items-center justify-center shrink-0 ${
                    isPositive ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-600'
                  }`}>
                    {isPositive ? <ArrowDownLeft className="w-4 h-4" /> : <ArrowUpRight className="w-4 h-4" />}
                  </div>
                  <div>
                    <p className="text-xs font-bold text-slate-900">{tx.description}</p>
                    <p className="text-[11px] text-slate-400">{tx.date}</p>
                  </div>
                </div>

                <span className={`text-xs font-bold font-mono ${
                  isPositive ? 'text-emerald-700' : 'text-slate-700'
                }`}>
                  {isPositive ? '+' : ''}{formatPrice(tx.amount)}
                </span>
              </div>
            );
          })}
        </div>
      </div>

      {/* Add Card Modal */}
      {isAddCardOpen && (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <div className="flex items-center gap-2">
                <CreditCard className="w-4 h-4 text-indigo-600" />
                <h3 className="text-base font-bold text-slate-900">Add Payment Method</h3>
              </div>
              <button
                onClick={() => setIsAddCardOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-700"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleAddCardSubmit} className="p-6 space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Card Type</label>
                <div className="grid grid-cols-3 gap-2">
                  {(['visa', 'mastercard', 'amex'] as const).map((t) => (
                    <button
                      key={t}
                      type="button"
                      onClick={() => setCardType(t)}
                      className={`py-2 rounded-xl text-xs font-bold uppercase border transition-all ${
                        cardType === t ? 'border-indigo-600 bg-indigo-50 text-indigo-950' : 'border-slate-200 text-slate-600'
                      }`}
                    >
                      {t}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Cardholder Full Name</label>
                <input
                  type="text"
                  value={cardholderName}
                  onChange={(e) => setCardholderName(e.target.value)}
                  className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500 uppercase"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Card Number</label>
                <input
                  type="text"
                  placeholder="4242 •••• •••• 4242"
                  value={cardNumber}
                  onChange={(e) => setCardNumber(e.target.value)}
                  className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500 font-mono"
                  required
                />
              </div>

              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Exp Month</label>
                  <input
                    type="text"
                    placeholder="MM"
                    maxLength={2}
                    value={expMonth}
                    onChange={(e) => setExpMonth(e.target.value)}
                    className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none text-center font-mono"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Exp Year</label>
                  <input
                    type="text"
                    placeholder="YY"
                    maxLength={2}
                    value={expYear}
                    onChange={(e) => setExpYear(e.target.value)}
                    className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none text-center font-mono"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">CVV</label>
                  <input
                    type="password"
                    placeholder="•••"
                    maxLength={4}
                    value={cvv}
                    onChange={(e) => setCvv(e.target.value)}
                    className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none text-center font-mono"
                    required
                  />
                </div>
              </div>

              <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer pt-1">
                <input
                  type="checkbox"
                  checked={isDefault}
                  onChange={(e) => setIsDefault(e.target.checked)}
                  className="w-4 h-4 text-indigo-600 rounded border-slate-300"
                />
                <span>Set as default payment card</span>
              </label>

              <div className="flex gap-2 justify-end pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setIsAddCardOpen(false)}
                  className="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold"
                >
                  Save Card
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
};
