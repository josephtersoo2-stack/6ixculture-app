import React, { useState } from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  X, 
  Trash2, 
  Plus, 
  Minus, 
  ShoppingBag, 
  ArrowRight, 
  ShieldCheck, 
  Truck, 
  Tag, 
  Check, 
  Sparkles,
  CreditCard
} from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';

export const CartDrawer: React.FC<{ onProceedToCheckout: () => void }> = ({ onProceedToCheckout }) => {
  const {
    cart,
    isCartOpen,
    setIsCartOpen,
    removeFromCart,
    updateCartQty,
    clearCart,
    appliedPromoCode,
    applyPromoCode,
    removePromoCode,
    promoDiscountPercent,
    promoDiscountAmount,
    cartSubtotal,
    cartTotal,
    cartItemsCount,
    formatPrice,
    rewardVouchers
  } = useDashboard();

  const [couponInput, setCouponInput] = useState('');
  const freeShippingThreshold = 150;
  const amountToFreeShipping = Math.max(0, freeShippingThreshold - cartSubtotal);
  const freeShippingProgress = Math.min(100, (cartSubtotal / freeShippingThreshold) * 100);

  const handleApplyCoupon = (e: React.FormEvent) => {
    e.preventDefault();
    if (!couponInput.trim()) return;
    if (applyPromoCode(couponInput)) {
      setCouponInput('');
    }
  };

  if (!isCartOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-hidden">
      {/* Backdrop */}
      <div 
        onClick={() => setIsCartOpen(false)}
        className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity animate-in fade-in"
      />

      <div className="fixed inset-y-0 right-0 max-w-full flex pl-10">
        <motion.div
          initial={{ x: '100%' }}
          animate={{ x: 0 }}
          exit={{ x: '100%' }}
          transition={{ type: 'spring', damping: 25, stiffness: 200 }}
          className="w-screen max-w-md bg-white shadow-2xl flex flex-col justify-between"
        >
          {/* Top Header */}
          <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center">
                <ShoppingBag className="w-4 h-4" />
              </div>
              <h2 className="text-base font-bold text-slate-900">
                Shopping Bag <span className="text-xs font-medium text-slate-500">({cartItemsCount} items)</span>
              </h2>
            </div>
            <button
              onClick={() => setIsCartOpen(false)}
              className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Free Shipping Meter */}
          <div className="px-5 py-3 bg-slate-50 border-b border-slate-100">
            <div className="flex items-center justify-between text-xs mb-1.5">
              <span className="font-semibold text-slate-700 flex items-center gap-1.5">
                <Truck className="w-3.5 h-3.5 text-indigo-600" />
                {amountToFreeShipping === 0 ? (
                  <span className="text-emerald-700 font-bold">You unlocked FREE Express Shipping! 🎉</span>
                ) : (
                  <span>Add <span className="font-bold text-indigo-600">{formatPrice(amountToFreeShipping)}</span> for Free Express Delivery</span>
                )}
              </span>
              <span className="font-bold text-slate-600">{Math.round(freeShippingProgress)}%</span>
            </div>
            <div className="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
              <div
                className="bg-indigo-600 h-full rounded-full transition-all duration-300"
                style={{ width: `${freeShippingProgress}%` }}
              ></div>
            </div>
          </div>

          {/* Items List */}
          <div className="flex-1 overflow-y-auto px-5 py-4 divide-y divide-slate-100">
            {cart.length === 0 ? (
              <div className="py-16 text-center">
                <div className="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                  <ShoppingBag className="w-8 h-8" />
                </div>
                <p className="text-sm font-semibold text-slate-800">Your bag is currently empty</p>
                <p className="text-xs text-slate-500 mt-1 max-w-xs mx-auto">
                  Explore recommendations or move items from your saved wishlist!
                </p>
              </div>
            ) : (
              cart.map((item) => (
                <div key={item.id} className="py-3.5 flex gap-3.5 items-start first:pt-0">
                  <img
                    src={item.image}
                    alt={item.title}
                    className="w-18 h-18 rounded-xl object-cover border border-slate-100 shrink-0"
                  />
                  <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <p className="text-[11px] font-semibold text-indigo-600 uppercase tracking-wider">
                          {item.brand}
                        </p>
                        <h4 className="text-xs font-bold text-slate-900 line-clamp-1">{item.title}</h4>
                      </div>
                      <button
                        onClick={() => removeFromCart(item.id)}
                        className="text-slate-400 hover:text-rose-600 p-1 transition-colors"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>

                    {(item.color || item.size) && (
                      <p className="text-[11px] text-slate-500 mt-0.5">
                        {item.color} {item.size ? `• Size ${item.size}` : ''}
                      </p>
                    )}

                    <div className="flex items-center justify-between mt-2.5">
                      <div className="flex items-center border border-slate-200 rounded-lg bg-white shadow-xs">
                        <button
                          onClick={() => updateCartQty(item.id, item.quantity - 1)}
                          className="p-1 text-slate-500 hover:text-slate-900 hover:bg-slate-50 rounded-l-md"
                        >
                          <Minus className="w-3 h-3" />
                        </button>
                        <span className="px-2.5 text-xs font-bold text-slate-800">
                          {item.quantity}
                        </span>
                        <button
                          onClick={() => updateCartQty(item.id, item.quantity + 1)}
                          className="p-1 text-slate-500 hover:text-slate-900 hover:bg-slate-50 rounded-r-md"
                        >
                          <Plus className="w-3 h-3" />
                        </button>
                      </div>

                      <div className="text-right">
                        <span className="text-xs font-bold text-slate-900">
                          {formatPrice(item.price * item.quantity)}
                        </span>
                        {item.originalPrice && (
                          <span className="text-[10px] text-slate-400 line-through ml-1.5">
                            {formatPrice(item.originalPrice * item.quantity)}
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>

          {/* Footer Calculations & Checkout */}
          {cart.length > 0 && (
            <div className="px-5 py-4 border-t border-slate-200/90 bg-slate-50/70 space-y-3">
              {/* Promo Coupon Form */}
              {appliedPromoCode ? (
                <div className="flex items-center justify-between p-2.5 bg-emerald-50 border border-emerald-200 rounded-xl text-xs">
                  <div className="flex items-center gap-2">
                    <Tag className="w-3.5 h-3.5 text-emerald-600" />
                    <span className="font-semibold text-emerald-900">
                      Code <span className="font-mono uppercase font-bold">{appliedPromoCode}</span> applied!
                    </span>
                  </div>
                  <button
                    onClick={removePromoCode}
                    className="text-[11px] font-bold text-rose-600 hover:underline"
                  >
                    Remove
                  </button>
                </div>
              ) : (
                <form onSubmit={handleApplyCoupon} className="flex gap-2">
                  <input
                    type="text"
                    placeholder="Enter promo or voucher code..."
                    value={couponInput}
                    onChange={(e) => setCouponInput(e.target.value)}
                    className="flex-1 bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-indigo-500 uppercase font-mono"
                  />
                  <button
                    type="submit"
                    className="px-3 py-1.5 bg-slate-900 text-white rounded-xl text-xs font-semibold hover:bg-slate-800 transition-colors"
                  >
                    Apply
                  </button>
                </form>
              )}

              {/* Price Breakdown */}
              <div className="space-y-1.5 text-xs text-slate-600 pt-1">
                <div className="flex justify-between">
                  <span>Bag Subtotal</span>
                  <span className="font-semibold text-slate-800">{formatPrice(cartSubtotal)}</span>
                </div>
                {promoDiscountPercent > 0 && (
                  <div className="flex justify-between text-emerald-600 font-medium">
                    <span>Loyalty Promo ({promoDiscountPercent}% OFF)</span>
                    <span>-{formatPrice(cartSubtotal * (promoDiscountPercent / 100))}</span>
                  </div>
                )}
                {promoDiscountAmount > 0 && (
                  <div className="flex justify-between text-emerald-600 font-medium">
                    <span>VIP Voucher Discount</span>
                    <span>-{formatPrice(promoDiscountAmount)}</span>
                  </div>
                )}
                <div className="flex justify-between">
                  <span>Shipping</span>
                  <span className="font-semibold text-emerald-600">
                    {amountToFreeShipping === 0 ? 'FREE' : formatPrice(12)}
                  </span>
                </div>
                <div className="flex justify-between text-sm font-bold text-slate-900 pt-2 border-t border-slate-200">
                  <span>Estimated Total</span>
                  <span className="text-base text-indigo-950 font-display font-bold">
                    {formatPrice(cartTotal)}
                  </span>
                </div>
              </div>

              {/* Checkout Button */}
              <button
                onClick={onProceedToCheckout}
                className="w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-md shadow-indigo-600/20 transition-all hover:scale-[1.01] active:scale-[0.99]"
              >
                <span>Proceed to Quick VIP Checkout</span>
                <ArrowRight className="w-4 h-4" />
              </button>

              <div className="flex items-center justify-center gap-4 text-[10px] text-slate-400 pt-1">
                <span className="flex items-center gap-1">
                  <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
                  256-Bit SSL Encrypted
                </span>
                <span>•</span>
                <span>30-Day Hassle-Free Returns</span>
              </div>
            </div>
          )}
        </motion.div>
      </div>
    </div>
  );
};
