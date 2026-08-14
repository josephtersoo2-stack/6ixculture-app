import React, { useState } from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  Award, 
  Sparkles, 
  Gift, 
  Share2, 
  Check, 
  Copy, 
  Clock, 
  Zap, 
  ShieldCheck, 
  Tag, 
  Truck, 
  Star,
  Users,
  ChevronRight
} from 'lucide-react';
import { RewardVoucher } from '../types/dashboard';

export const RewardsView: React.FC = () => {
  const {
    user,
    rewardVouchers,
    redeemRewardVoucher,
    referralCode,
    formatPrice,
    showToast
  } = useDashboard();

  const [copiedReferral, setCopiedReferral] = useState(false);
  const [copiedCodeId, setCopiedCodeId] = useState<string | null>(null);

  const handleCopyReferral = () => {
    navigator.clipboard.writeText(`https://aura.luxury/join?ref=${referralCode}`);
    setCopiedReferral(true);
    showToast('Referral Link Copied', 'Share this link to give friends $20 off and earn $20 store credit!', 'success');
    setTimeout(() => setCopiedReferral(false), 2500);
  };

  const handleCopyCode = (voucher: RewardVoucher) => {
    navigator.clipboard.writeText(voucher.code);
    setCopiedCodeId(voucher.id);
    showToast('Promo Code Copied!', `Code "${voucher.code}" ready to paste at checkout.`, 'success');
    setTimeout(() => setCopiedCodeId(null), 2000);
  };

  const tiers = [
    { name: 'Silver', points: '0 - 999 pts', unlocked: true, perks: ['1x Points on Purchases', 'Standard Shipping', 'Birthday Gift'] },
    { name: 'Gold', points: '1,000 - 2,499 pts', unlocked: true, perks: ['1.25x Points', 'Priority Packaging', 'Early Sale Access'] },
    { name: 'Platinum', points: '2,500 - 4,999 pts', unlocked: true, current: true, perks: ['1.5x Points', 'Free Express Shipping', 'Dedicated 24/7 Concierge', '$35 Annual Birthday Voucher'] },
    { name: 'Diamond', points: '5,000+ pts', unlocked: false, perks: ['2x Points', 'Free Same-Day Delivery', 'Private Showroom Invites', 'Complimentary Bespoke Monogramming'] }
  ];

  const progressPercent = Math.min(100, Math.round((user.tierPoints / user.nextTierPoints) * 100));

  return (
    <div className="space-y-6 max-w-7xl mx-auto pb-12">
      
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-display font-bold text-slate-900 tracking-tight">
            VIP Rewards Club & Perks
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-0.5">
            Earn points with every purchase, redeem exclusive discount vouchers, and unlock luxury tiers.
          </p>
        </div>

        <div className="flex items-center gap-2 bg-amber-50 border border-amber-200 px-4 py-2 rounded-2xl">
          <Sparkles className="w-4 h-4 text-amber-600" />
          <span className="text-xs font-bold text-amber-950">
            {user.tierPoints.toLocaleString()} Points Available
          </span>
        </div>
      </div>

      {/* Hero Tier Progress Banner */}
      <div className="rounded-3xl p-6 sm:p-8 bg-gradient-to-r from-slate-950 via-indigo-950 to-slate-900 text-white shadow-xl relative overflow-hidden">
        <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div className="space-y-2">
            <span className="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-amber-400/20 text-amber-300 border border-amber-400/30 inline-flex items-center gap-1.5">
              <Award className="w-3.5 h-3.5" />
              Current Status: {user.tier} Tier
            </span>

            <h2 className="text-2xl font-display font-bold text-white">
              {(user.nextTierPoints - user.tierPoints).toLocaleString()} Points to Diamond Tier
            </h2>

            <div className="max-w-md w-full pt-2">
              <div className="flex items-center justify-between text-xs text-slate-300 mb-1.5 font-semibold">
                <span>{user.tier} ({user.tierPoints} pts)</span>
                <span>Diamond ({user.nextTierPoints} pts)</span>
              </div>
              <div className="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                <div 
                  className="bg-gradient-to-r from-indigo-400 to-amber-300 h-full rounded-full transition-all duration-500"
                  style={{ width: `${progressPercent}%` }}
                />
              </div>
            </div>
          </div>

          <div className="p-4 rounded-2xl bg-white/10 backdrop-blur-md border border-white/10 text-xs space-y-2 shrink-0 max-w-xs">
            <p className="font-bold text-white flex items-center gap-1.5">
              <Zap className="w-4 h-4 text-amber-300" />
              Active Tier Privileges
            </p>
            <ul className="space-y-1 text-[11px] text-slate-200">
              <li>✓ Free FedEx Express Shipping on all orders</li>
              <li>✓ 1.5x Multiplier on points earned</li>
              <li>✓ Dedicated Priority Concierge desk</li>
            </ul>
          </div>
        </div>
      </div>

      {/* Tier Breakdown Matrix */}
      <div className="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-sm space-y-4">
        <h3 className="text-base font-bold text-slate-900">VIP Membership Tiers</h3>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {tiers.map((t) => (
            <div
              key={t.name}
              className={`rounded-2xl p-5 border transition-all flex flex-col justify-between ${
                t.current 
                  ? 'border-indigo-600 bg-indigo-50/40 ring-2 ring-indigo-500/10 shadow-xs' 
                  : t.unlocked 
                  ? 'border-slate-200 bg-slate-50/50' 
                  : 'border-slate-200/80 bg-white opacity-85'
              }`}
            >
              <div>
                <div className="flex items-center justify-between mb-2">
                  <h4 className="text-sm font-bold text-slate-900">{t.name}</h4>
                  {t.current && (
                    <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-600 text-white">
                      Your Tier
                    </span>
                  )}
                </div>
                <p className="text-xs font-semibold text-slate-500 mb-3">{t.points}</p>

                <ul className="space-y-1.5 text-xs text-slate-600">
                  {t.perks.map((p, idx) => (
                    <li key={idx} className="flex items-start gap-1.5">
                      <Check className={`w-3.5 h-3.5 mt-0.5 shrink-0 ${t.unlocked ? 'text-indigo-600' : 'text-slate-400'}`} />
                      <span>{p}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Rewards Voucher Store */}
      <div className="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-sm space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <h3 className="text-base font-bold text-slate-900">Redeem Points for Vouchers</h3>
            <p className="text-xs text-slate-500">Claim coupon codes to use immediately at checkout</p>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {rewardVouchers.map((voucher) => {
            const canAfford = user.tierPoints >= voucher.pointsCost;
            const isRedeemed = !!voucher.redeemedDate;
            const isCopied = copiedCodeId === voucher.id;

            return (
              <div
                key={voucher.id}
                className="p-5 rounded-2xl border border-slate-200 hover:border-slate-300 transition-colors bg-white flex flex-col justify-between space-y-4"
              >
                <div>
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-0.5 rounded-md border border-indigo-100">
                      {voucher.discountText}
                    </span>
                    <span className="text-xs font-bold text-slate-900 flex items-center gap-1">
                      <Sparkles className="w-3.5 h-3.5 text-amber-500" />
                      {voucher.pointsCost} pts
                    </span>
                  </div>

                  <h4 className="text-sm font-bold text-slate-900 mt-2">{voucher.title}</h4>
                  <p className="text-xs text-slate-500 mt-0.5">
                    Min. spend {formatPrice(voucher.minSpend)} • Expires {voucher.expiresAt}
                  </p>
                </div>

                <div className="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                  {isRedeemed ? (
                    <div className="flex-1 flex items-center justify-between bg-slate-50 p-2 rounded-xl border border-slate-200">
                      <span className="font-mono text-xs font-bold text-slate-900">{voucher.code}</span>
                      <button
                        onClick={() => handleCopyCode(voucher)}
                        className="text-xs font-bold text-indigo-600 hover:underline flex items-center gap-1"
                      >
                        {isCopied ? <Check className="w-3.5 h-3.5 text-emerald-600" /> : <Copy className="w-3.5 h-3.5" />}
                        {isCopied ? 'Copied' : 'Copy'}
                      </button>
                    </div>
                  ) : (
                    <button
                      onClick={() => redeemRewardVoucher(voucher.id)}
                      disabled={!canAfford}
                      className={`w-full py-2 px-3 rounded-xl text-xs font-bold transition-colors ${
                        canAfford
                          ? 'bg-slate-900 hover:bg-black text-white'
                          : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                      }`}
                    >
                      {canAfford ? `Redeem (${voucher.pointsCost} pts)` : `Need ${voucher.pointsCost - user.tierPoints} more pts`}
                    </button>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Referral Program Banner */}
      <div className="rounded-3xl p-6 sm:p-8 bg-gradient-to-br from-indigo-900 via-indigo-950 to-slate-950 text-white shadow-md flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Users className="w-4 h-4 text-sky-400" />
            <span className="text-xs font-bold uppercase tracking-wider text-sky-300">
              VIP Referral Circle
            </span>
          </div>
          <h3 className="text-xl font-display font-bold text-white">
            Give $20 to Friends, Get $20 in Aura Store Credit
          </h3>
          <p className="text-xs text-slate-300 max-w-lg">
            Share your private invitation link. When they place their first order over $100, you will automatically receive $20 credited straight to your wallet.
          </p>
        </div>

        <div className="flex items-center gap-2 bg-white/10 p-2 rounded-2xl backdrop-blur-md border border-white/10 shrink-0">
          <span className="font-mono text-xs font-bold px-3 text-white">
            {referralCode}
          </span>
          <button
            onClick={handleCopyReferral}
            className="px-4 py-2 rounded-xl bg-white text-slate-950 text-xs font-bold hover:bg-slate-100 transition-colors flex items-center gap-1.5 shadow-sm"
          >
            {copiedReferral ? <Check className="w-3.5 h-3.5 text-emerald-600" /> : <Copy className="w-3.5 h-3.5" />}
            <span>{copiedReferral ? 'Copied!' : 'Copy Invite Link'}</span>
          </button>
        </div>
      </div>

    </div>
  );
};
