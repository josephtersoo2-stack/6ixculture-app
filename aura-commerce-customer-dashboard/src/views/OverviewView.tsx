import React from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  Package, 
  Heart, 
  CreditCard, 
  Award, 
  Truck, 
  ArrowRight, 
  Sparkles, 
  Clock, 
  CheckCircle2, 
  ChevronRight, 
  Tag, 
  Star, 
  TrendingUp, 
  ShoppingBag,
  ExternalLink,
  ShieldCheck
} from 'lucide-react';
import { RECOMMENDATIONS } from '../data/mockData';

export const OverviewView: React.FC = () => {
  const {
    user,
    orders,
    wishlist,
    walletBalance,
    formatPrice,
    setActiveTab,
    openOrderDetails,
    reorderItems,
    addToCart,
    toggleWishlist,
    showToast
  } = useDashboard();

  // Find active orders in transit
  const inTransitOrder = orders.find(o => o.status === 'out_for_delivery') || orders.find(o => o.status === 'shipped') || orders[0];
  const recentOrders = orders.slice(0, 3);
  const activeCount = orders.filter(o => o.status === 'out_for_delivery' || o.status === 'shipped' || o.status === 'processing').length;
  const priceDroppedItems = wishlist.filter(w => w.priceDropped);

  return (
    <div className="space-y-6 max-w-7xl mx-auto pb-12">
      
      {/* Welcome & Loyalty Tier Banner */}
      <div className="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-950 via-indigo-950 to-slate-900 text-white p-6 sm:p-8 shadow-xl shadow-slate-950/20 border border-slate-800">
        <div className="absolute top-0 right-0 -mt-8 -mr-8 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div className="absolute bottom-0 right-1/4 -mb-10 w-48 h-48 bg-sky-500/10 rounded-full blur-2xl pointer-events-none"></div>

        <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div className="space-y-2">
            <div className="flex items-center gap-2">
              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-amber-400/20 text-amber-300 border border-amber-400/30">
                <Sparkles className="w-3 h-3 text-amber-300" />
                {user.tier} VIP Member
              </span>
              <span className="text-xs text-slate-400">• Member #{user.id.replace('usr_', '')}</span>
            </div>

            <h1 className="text-2xl sm:text-3xl font-display font-bold text-white tracking-tight">
              Welcome back, {user.firstName}!
            </h1>
            <p className="text-xs sm:text-sm text-slate-300 max-w-xl leading-relaxed">
              You have <span className="font-semibold text-white">{activeCount} order(s) in active delivery</span>. Your VIP points balance gives you access to exclusive perks.
            </p>
          </div>

          {/* Quick VIP Stats Widget */}
          <div className="flex items-center gap-4 bg-white/10 p-4 rounded-2xl backdrop-blur-md border border-white/10 shrink-0">
            <div>
              <p className="text-[11px] text-slate-300 uppercase tracking-wider font-semibold">
                Loyalty Points
              </p>
              <p className="text-xl sm:text-2xl font-display font-bold text-amber-300 mt-0.5">
                {user.tierPoints.toLocaleString()} <span className="text-xs text-white font-normal">pts</span>
              </p>
              <p className="text-[10px] text-slate-300 mt-0.5">
                Worth <span className="text-white font-bold">{formatPrice(85)}</span> in rewards
              </p>
            </div>
            <button
              onClick={() => setActiveTab('rewards')}
              className="py-2 px-3 rounded-xl bg-white text-slate-950 text-xs font-bold hover:bg-slate-100 transition-transform active:scale-95 shadow-sm flex items-center gap-1"
            >
              <span>Redeem</span>
              <ChevronRight className="w-3 h-3" />
            </button>
          </div>
        </div>
      </div>

      {/* 4 Summary Stat Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4">
        
        {/* Active Shipments Card */}
        <div 
          onClick={() => setActiveTab('orders')}
          className="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/80 hover:border-indigo-400 hover:shadow-md transition-all cursor-pointer group"
        >
          <div className="flex items-center justify-between">
            <div className="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:scale-105 transition-transform">
              <Truck className="w-5 h-5" />
            </div>
            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">
              Live
            </span>
          </div>
          <p className="text-xs font-semibold text-slate-500 mt-3">Active Shipments</p>
          <p className="text-xl sm:text-2xl font-display font-bold text-slate-900 mt-0.5">
            {activeCount} <span className="text-xs font-normal text-slate-400">in transit</span>
          </p>
          <p className="text-[11px] text-indigo-600 font-semibold mt-1.5 flex items-center gap-1 group-hover:underline">
            Track parcels <ArrowRight className="w-3 h-3" />
          </p>
        </div>

        {/* Saved Wishlist Card */}
        <div 
          onClick={() => setActiveTab('wishlist')}
          className="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/80 hover:border-rose-400 hover:shadow-md transition-all cursor-pointer group"
        >
          <div className="flex items-center justify-between">
            <div className="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center group-hover:scale-105 transition-transform">
              <Heart className="w-5 h-5" />
            </div>
            {priceDroppedItems.length > 0 && (
              <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">
                {priceDroppedItems.length} On Sale
              </span>
            )}
          </div>
          <p className="text-xs font-semibold text-slate-500 mt-3">Saved Items</p>
          <p className="text-xl sm:text-2xl font-display font-bold text-slate-900 mt-0.5">
            {wishlist.length} <span className="text-xs font-normal text-slate-400">products</span>
          </p>
          <p className="text-[11px] text-rose-600 font-semibold mt-1.5 flex items-center gap-1 group-hover:underline">
            View collections <ArrowRight className="w-3 h-3" />
          </p>
        </div>

        {/* Wallet & Store Credit Card */}
        <div 
          onClick={() => setActiveTab('wallet')}
          className="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/80 hover:border-emerald-400 hover:shadow-md transition-all cursor-pointer group"
        >
          <div className="flex items-center justify-between">
            <div className="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-105 transition-transform">
              <CreditCard className="w-5 h-5" />
            </div>
            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
              Ready
            </span>
          </div>
          <p className="text-xs font-semibold text-slate-500 mt-3">Store Credit Balance</p>
          <p className="text-xl sm:text-2xl font-display font-bold text-slate-900 mt-0.5">
            {formatPrice(walletBalance)}
          </p>
          <p className="text-[11px] text-emerald-700 font-semibold mt-1.5 flex items-center gap-1 group-hover:underline">
            Redeem gift card <ArrowRight className="w-3 h-3" />
          </p>
        </div>

        {/* VIP Rewards Club */}
        <div 
          onClick={() => setActiveTab('rewards')}
          className="p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/80 hover:border-amber-400 hover:shadow-md transition-all cursor-pointer group"
        >
          <div className="flex items-center justify-between">
            <div className="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:scale-105 transition-transform">
              <Award className="w-5 h-5" />
            </div>
            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">
              {user.tier}
            </span>
          </div>
          <p className="text-xs font-semibold text-slate-500 mt-3">VIP Club Tier</p>
          <p className="text-xl sm:text-2xl font-display font-bold text-slate-900 mt-0.5">
            {user.tier} <span className="text-xs font-normal text-slate-400">Level</span>
          </p>
          <p className="text-[11px] text-amber-700 font-semibold mt-1.5 flex items-center gap-1 group-hover:underline">
            View perks & vouchers <ArrowRight className="w-3 h-3" />
          </p>
        </div>

      </div>

      {/* Main Grid: Active Shipment Live Tracker + Price Drop Alert Banner */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {/* Left 2 Cols: Live Order Tracker Hero */}
        {inTransitOrder && (
          <div className="lg:col-span-2 bg-white rounded-3xl p-6 border border-slate-200/90 shadow-sm space-y-5">
            <div className="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
              <div>
                <span className="text-[11px] font-bold text-indigo-600 uppercase tracking-wider">
                  Live Shipment Priority Tracker
                </span>
                <h3 className="text-base font-bold text-slate-900 mt-0.5 flex items-center gap-2">
                  <span>Order #{inTransitOrder.orderNumber}</span>
                  <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-sky-100 text-sky-800">
                    {inTransitOrder.status.replace('_', ' ')}
                  </span>
                </h3>
              </div>
              <button
                onClick={() => openOrderDetails(inTransitOrder.id)}
                className="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1"
              >
                <span>Full Tracking Timeline</span>
                <ChevronRight className="w-3.5 h-3.5" />
              </button>
            </div>

            {/* Courier & Delivery ETA Callout */}
            <div className="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                  <Truck className="w-5 h-5 text-sky-400" />
                </div>
                <div>
                  <p className="text-xs font-bold text-slate-900">{inTransitOrder.carrier}</p>
                  <p className="text-[11px] text-slate-500 font-mono">Tracking: {inTransitOrder.trackingNumber}</p>
                </div>
              </div>

              <div className="text-left sm:text-right">
                <p className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Estimated Delivery</p>
                <p className="text-xs sm:text-sm font-bold text-slate-900 text-emerald-700">
                  {inTransitOrder.estimatedDelivery}
                </p>
              </div>
            </div>

            {/* Visual Step Progress Bar */}
            <div className="py-2">
              <div className="grid grid-cols-4 gap-2 relative">
                {['Ordered', 'Processed', 'In Transit', 'Out for Delivery'].map((step, idx) => {
                  const isComplete = idx <= 2 || inTransitOrder.status === 'out_for_delivery';
                  const isCurrent = (idx === 3 && inTransitOrder.status === 'out_for_delivery') || (idx === 2 && inTransitOrder.status === 'shipped');
                  return (
                    <div key={step} className="flex flex-col items-center text-center">
                      <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-colors ${
                        isCurrent ? 'bg-sky-500 text-white ring-4 ring-sky-100' :
                        isComplete ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-400'
                      }`}>
                        {isComplete ? <CheckCircle2 className="w-4 h-4" /> : idx + 1}
                      </div>
                      <span className={`text-[11px] font-semibold mt-2 ${isCurrent ? 'text-sky-700 font-bold' : isComplete ? 'text-slate-800' : 'text-slate-400'}`}>
                        {step}
                      </span>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* In-Transit Items Preview */}
            <div className="pt-2 border-t border-slate-100">
              <p className="text-xs font-bold text-slate-700 mb-2">Parcel Contents</p>
              <div className="flex flex-wrap gap-2.5">
                {inTransitOrder.items.map((item) => (
                  <div
                    key={item.id}
                    onClick={() => openOrderDetails(inTransitOrder.id)}
                    className="flex items-center gap-2.5 p-2 rounded-xl border border-slate-200 hover:border-slate-300 bg-slate-50/50 cursor-pointer transition-colors"
                  >
                    <img src={item.image} alt={item.title} className="w-9 h-9 rounded-lg object-cover" />
                    <div className="min-w-0">
                      <p className="text-xs font-bold text-slate-800 truncate max-w-[140px] sm:max-w-[180px]">
                        {item.title}
                      </p>
                      <p className="text-[10px] text-slate-500">Qty: {item.quantity}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {/* Right 1 Col: Price Drop & Restock dynamic alert */}
        <div className="space-y-4">
          
          {/* Wishlist Sale Alert */}
          {priceDroppedItems.length > 0 && (
            <div className="p-5 rounded-3xl bg-gradient-to-br from-rose-50 to-amber-50 border border-rose-200/80 shadow-xs space-y-3">
              <div className="flex items-center gap-2">
                <Tag className="w-4 h-4 text-rose-600" />
                <span className="text-xs font-bold uppercase tracking-wider text-rose-800">
                  Wishlist Price Drop Alert
                </span>
              </div>
              
              <div className="flex items-center gap-3">
                <img
                  src={priceDroppedItems[0].image}
                  alt={priceDroppedItems[0].title}
                  className="w-14 h-14 rounded-xl object-cover border border-rose-200"
                />
                <div className="min-w-0 flex-1">
                  <h4 className="text-xs font-bold text-slate-900 truncate">
                    {priceDroppedItems[0].title}
                  </h4>
                  <div className="flex items-baseline gap-2 mt-0.5">
                    <span className="text-sm font-bold text-rose-600">
                      {formatPrice(priceDroppedItems[0].price)}
                    </span>
                    {priceDroppedItems[0].previousPrice && (
                      <span className="text-xs text-slate-400 line-through">
                        {formatPrice(priceDroppedItems[0].previousPrice)}
                      </span>
                    )}
                  </div>
                  <p className="text-[10px] font-semibold text-emerald-700 mt-0.5">
                    Only {priceDroppedItems[0].stockCount} units remaining
                  </p>
                </div>
              </div>

              <button
                onClick={() => {
                  addToCart({
                    productId: priceDroppedItems[0].productId,
                    title: priceDroppedItems[0].title,
                    brand: priceDroppedItems[0].brand,
                    price: priceDroppedItems[0].price,
                    originalPrice: priceDroppedItems[0].originalPrice,
                    image: priceDroppedItems[0].image,
                    quantity: 1
                  });
                  showToast('Added to Cart', `${priceDroppedItems[0].title} added to bag!`);
                }}
                className="w-full py-2 px-3 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold flex items-center justify-center gap-2 transition-colors shadow-xs"
              >
                <ShoppingBag className="w-3.5 h-3.5" />
                <span>Add to Bag with Discount</span>
              </button>
            </div>
          )}

          {/* Quick Support & Warranty Box */}
          <div className="p-5 rounded-3xl bg-white border border-slate-200/90 shadow-sm space-y-3">
            <div className="flex items-center gap-2">
              <ShieldCheck className="w-4 h-4 text-emerald-600" />
              <span className="text-xs font-bold text-slate-800">
                Aura VIP Protection
              </span>
            </div>
            <p className="text-xs text-slate-500 leading-relaxed">
              All orders include complimentary 2-year warranty, express parcel protection, and free 30-day doorstep returns.
            </p>
            <button
              onClick={() => setActiveTab('support')}
              className="text-xs font-bold text-indigo-600 hover:underline flex items-center gap-1"
            >
              <span>Contact dedicated concierge</span>
              <ArrowRight className="w-3 h-3" />
            </button>
          </div>
        </div>

      </div>

      {/* Recent Orders Snapshot Shelf */}
      <div className="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-sm space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <h3 className="text-base font-bold text-slate-900">Recent Purchase History</h3>
            <p className="text-xs text-slate-500">Quickly reorder, view receipts, or manage returns</p>
          </div>
          <button
            onClick={() => setActiveTab('orders')}
            className="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1"
          >
            <span>View All ({orders.length})</span>
            <ChevronRight className="w-3.5 h-3.5" />
          </button>
        </div>

        <div className="space-y-3">
          {recentOrders.map((order) => (
            <div
              key={order.id}
              className="p-4 rounded-2xl border border-slate-200 hover:border-slate-300 transition-colors flex flex-col md:flex-row md:items-center justify-between gap-4"
            >
              <div className="flex items-center gap-3.5 min-w-0">
                <div className="flex -space-x-4 shrink-0 overflow-hidden py-1">
                  {order.items.slice(0, 3).map((item, idx) => (
                    <img
                      key={item.id}
                      src={item.image}
                      alt={item.title}
                      className="w-12 h-12 rounded-xl object-cover ring-2 ring-white border border-slate-200 shrink-0"
                    />
                  ))}
                </div>

                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="text-xs font-bold text-slate-900">Order #{order.orderNumber}</span>
                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full capitalize ${
                      order.status === 'delivered' ? 'bg-emerald-100 text-emerald-800' :
                      order.status === 'out_for_delivery' ? 'bg-sky-100 text-sky-800' :
                      order.status === 'shipped' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-700'
                    }`}>
                      {order.status.replace('_', ' ')}
                    </span>
                  </div>
                  <p className="text-xs text-slate-500 mt-0.5 truncate">
                    {order.items.map(i => i.title).join(', ')}
                  </p>
                  <p className="text-[11px] text-slate-400">
                    {order.date} • Total: <span className="font-bold text-slate-700">{formatPrice(order.total)}</span>
                  </p>
                </div>
              </div>

              {/* Action Buttons */}
              <div className="flex items-center gap-2 self-end md:self-center shrink-0">
                <button
                  onClick={() => openOrderDetails(order.id)}
                  className="px-3.5 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-xs font-semibold text-slate-700 transition-colors"
                >
                  View Details
                </button>
                <button
                  onClick={() => reorderItems(order)}
                  className="px-3.5 py-1.5 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold flex items-center gap-1.5 transition-colors shadow-xs"
                >
                  <ShoppingBag className="w-3.5 h-3.5" />
                  <span>Buy Again</span>
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Recommended For You Shelf */}
      <div className="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-sm space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <span className="text-[11px] font-bold text-indigo-600 uppercase tracking-wider">
              Curated Selection
            </span>
            <h3 className="text-base font-bold text-slate-900 mt-0.5">
              Recommended for Your Taste & Lifestyle
            </h3>
          </div>
          <button
            onClick={() => setActiveTab('wishlist')}
            className="text-xs font-bold text-indigo-600 hover:text-indigo-800"
          >
            Explore Wishlist
          </button>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {RECOMMENDATIONS.map((product) => (
            <div
              key={product.id}
              className="rounded-2xl border border-slate-200/80 p-3 hover:border-indigo-400 hover:shadow-md transition-all flex flex-col justify-between group"
            >
              <div>
                <div className="relative overflow-hidden rounded-xl bg-slate-100 aspect-square mb-2.5">
                  <img
                    src={product.image}
                    alt={product.title}
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                  {product.badge && (
                    <span className="absolute top-2 left-2 text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-950/80 backdrop-blur-xs text-white">
                      {product.badge}
                    </span>
                  )}
                  <button
                    onClick={() => toggleWishlist({
                      productId: product.id,
                      title: product.title,
                      brand: product.brand,
                      price: product.price,
                      originalPrice: product.originalPrice,
                      image: product.image,
                      rating: product.rating,
                      category: product.category
                    })}
                    className="absolute top-2 right-2 p-1.5 rounded-full bg-white/80 backdrop-blur-xs text-slate-700 hover:text-rose-600 transition-colors shadow-xs"
                    title="Save to Wishlist"
                  >
                    <Heart className="w-3.5 h-3.5" />
                  </button>
                </div>

                <p className="text-[11px] font-semibold text-indigo-600 uppercase tracking-wider">{product.brand}</p>
                <h4 className="text-xs font-bold text-slate-900 line-clamp-1">{product.title}</h4>

                <div className="flex items-center gap-1 mt-1 text-[11px] text-slate-500">
                  <Star className="w-3 h-3 text-amber-400 fill-amber-400" />
                  <span className="font-bold text-slate-700">{product.rating}</span>
                  <span>({product.reviewsCount})</span>
                </div>
              </div>

              <div className="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between">
                <div>
                  <span className="text-xs font-bold text-slate-900">
                    {formatPrice(product.price)}
                  </span>
                  {product.originalPrice && (
                    <span className="text-[10px] text-slate-400 line-through ml-1.5">
                      {formatPrice(product.originalPrice)}
                    </span>
                  )}
                </div>

                <button
                  onClick={() => {
                    addToCart({
                      productId: product.id,
                      title: product.title,
                      brand: product.brand,
                      price: product.price,
                      originalPrice: product.originalPrice,
                      image: product.image,
                      quantity: 1
                    });
                    showToast('Added to Cart', `${product.title} is now in your bag.`);
                  }}
                  className="p-2 rounded-xl bg-slate-900 hover:bg-black text-white transition-colors"
                  title="Add to Shopping Bag"
                >
                  <ShoppingBag className="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>

    </div>
  );
};
