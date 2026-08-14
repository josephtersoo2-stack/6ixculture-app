import React, { useState } from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  Package, 
  Search, 
  Filter, 
  Truck, 
  RotateCcw, 
  Star, 
  CheckCircle2, 
  Clock, 
  AlertCircle, 
  ShoppingBag, 
  ChevronRight, 
  FileText,
  Calendar,
  X
} from 'lucide-react';
import { Order, OrderItem } from '../types/dashboard';

export const OrdersView: React.FC<{
  onOpenReturn: (orderId: string, item: OrderItem) => void;
  onOpenReview: (orderId: string, item: OrderItem) => void;
}> = ({ onOpenReturn, onOpenReview }) => {
  const {
    orders,
    formatPrice,
    openOrderDetails,
    reorderItems,
    cancelOrder
  } = useDashboard();

  const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'delivered' | 'returned'>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [yearFilter, setYearFilter] = useState('all');

  const filteredOrders = orders.filter((order) => {
    // Status filter
    if (statusFilter === 'active') {
      if (order.status !== 'processing' && order.status !== 'shipped' && order.status !== 'out_for_delivery') {
        return false;
      }
    } else if (statusFilter === 'delivered') {
      if (order.status !== 'delivered') return false;
    } else if (statusFilter === 'returned') {
      if (order.status !== 'returned' && order.status !== 'cancelled') return false;
    }

    // Search query
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      const matchNumber = order.orderNumber.toLowerCase().includes(q);
      const matchCarrier = order.carrier.toLowerCase().includes(q);
      const matchItems = order.items.some(
        (i) => i.title.toLowerCase().includes(q) || i.brand.toLowerCase().includes(q)
      );
      if (!matchNumber && !matchCarrier && !matchItems) return false;
    }

    return true;
  });

  const activeCount = orders.filter(
    (o) => o.status === 'processing' || o.status === 'shipped' || o.status === 'out_for_delivery'
  ).length;
  const deliveredCount = orders.filter((o) => o.status === 'delivered').length;
  const returnedCount = orders.filter((o) => o.status === 'returned' || o.status === 'cancelled').length;

  return (
    <div className="space-y-6 max-w-7xl mx-auto pb-12">
      
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-display font-bold text-slate-900 tracking-tight">
            Orders & Shipments
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-0.5">
            Track packages in real-time, initiate returns, download invoices, and reorder.
          </p>
        </div>
      </div>

      {/* Filter Tabs & Search Controls */}
      <div className="bg-white rounded-3xl p-4 sm:p-5 border border-slate-200/90 shadow-sm space-y-4">
        
        {/* Status Pills */}
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap gap-1.5 sm:gap-2">
            {[
              { id: 'all', label: 'All Orders', count: orders.length },
              { id: 'active', label: 'In Transit / Active', count: activeCount, alert: activeCount > 0 },
              { id: 'delivered', label: 'Delivered', count: deliveredCount },
              { id: 'returned', label: 'Returns & Cancelled', count: returnedCount }
            ].map((tab) => {
              const isSelected = statusFilter === tab.id;
              return (
                <button
                  key={tab.id}
                  onClick={() => setStatusFilter(tab.id as any)}
                  className={`px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all ${
                    isSelected
                      ? 'bg-slate-900 text-white shadow-xs'
                      : 'bg-slate-100/80 text-slate-600 hover:bg-slate-200/70'
                  }`}
                >
                  <span>{tab.label}</span>
                  <span className={`text-[10px] font-bold px-1.5 py-0.2 rounded-md ${
                    isSelected ? 'bg-slate-800 text-slate-200' : 'bg-white text-slate-700'
                  }`}>
                    {tab.count}
                  </span>
                </button>
              );
            })}
          </div>
        </div>

        {/* Search Bar & Date Filter */}
        <div className="flex flex-col sm:flex-row gap-3 pt-2 border-t border-slate-100">
          <div className="flex-1 relative">
            <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              placeholder="Search by order #, item name, or brand..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-9 pr-8 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:bg-white transition-all"
            />
            {searchQuery && (
              <button
                onClick={() => setSearchQuery('')}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
              >
                <X className="w-3.5 h-3.5" />
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Orders List */}
      <div className="space-y-4">
        {filteredOrders.length === 0 ? (
          <div className="bg-white rounded-3xl p-12 text-center border border-slate-200/90 shadow-sm">
            <div className="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
              <Package className="w-8 h-8" />
            </div>
            <h3 className="text-base font-bold text-slate-900">No orders found</h3>
            <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
              We couldn't find any orders matching your selected filters or search terms.
            </p>
            <button
              onClick={() => {
                setStatusFilter('all');
                setSearchQuery('');
              }}
              className="mt-4 px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-black transition-colors"
            >
              Reset Filters
            </button>
          </div>
        ) : (
          filteredOrders.map((order) => {
            const isDelivered = order.status === 'delivered';
            const isInTransit = order.status === 'out_for_delivery' || order.status === 'shipped';
            const isProcessing = order.status === 'processing';
            const isReturned = order.status === 'returned';
            const isCancelled = order.status === 'cancelled';

            return (
              <div
                key={order.id}
                className="bg-white rounded-3xl border border-slate-200/90 overflow-hidden shadow-xs hover:shadow-md hover:border-slate-300 transition-all"
              >
                {/* Order Top Bar */}
                <div className="p-4 sm:p-5 bg-slate-50/70 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                  <div className="flex flex-wrap items-center gap-3 sm:gap-6 text-xs">
                    <div>
                      <p className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Order Placed</p>
                      <p className="font-bold text-slate-900 mt-0.5">{order.date}</p>
                    </div>
                    <div>
                      <p className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Total Amount</p>
                      <p className="font-bold text-slate-900 mt-0.5">{formatPrice(order.total)}</p>
                    </div>
                    <div className="hidden sm:block">
                      <p className="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Ship To</p>
                      <p className="font-bold text-slate-900 mt-0.5">{order.shippingAddress.recipientName}</p>
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    <span className="font-mono text-xs font-bold text-slate-800 bg-white px-2.5 py-1 rounded-lg border border-slate-200">
                      #{order.orderNumber}
                    </span>
                    <span className={`text-xs font-semibold px-2.5 py-1 rounded-full capitalize ${
                      isDelivered ? 'bg-emerald-100 text-emerald-800' :
                      isInTransit ? 'bg-sky-100 text-sky-800' :
                      isProcessing ? 'bg-amber-100 text-amber-800' :
                      isReturned ? 'bg-purple-100 text-purple-800' : 'bg-rose-100 text-rose-800'
                    }`}>
                      {order.status.replace('_', ' ')}
                    </span>
                  </div>
                </div>

                {/* Tracking ETA Alert banner */}
                {isInTransit && (
                  <div className="px-5 py-3 bg-sky-50 border-b border-sky-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                    <div className="flex items-center gap-2 text-sky-900">
                      <Truck className="w-4 h-4 text-sky-600 shrink-0" />
                      <span className="font-semibold">
                        {order.carrier} • {order.estimatedDelivery}
                      </span>
                    </div>
                    <button
                      onClick={() => openOrderDetails(order.id)}
                      className="text-sky-700 hover:text-sky-900 font-bold flex items-center gap-1 self-start sm:self-auto"
                    >
                      <span>Track Package Live</span>
                      <ChevronRight className="w-3.5 h-3.5" />
                    </button>
                  </div>
                )}

                {/* Items in Order */}
                <div className="p-5 space-y-4">
                  {order.items.map((item) => (
                    <div
                      key={item.id}
                      className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 py-2 first:pt-0 last:pb-0"
                    >
                      <div className="flex items-center gap-4 min-w-0">
                        <img
                          src={item.image}
                          alt={item.title}
                          className="w-16 h-16 rounded-2xl object-cover border border-slate-100 shrink-0"
                        />
                        <div className="min-w-0">
                          <p className="text-[11px] font-semibold text-indigo-600 uppercase tracking-wider">
                            {item.brand}
                          </p>
                          <h4 className="text-xs sm:text-sm font-bold text-slate-900 truncate">
                            {item.title}
                          </h4>
                          <p className="text-xs text-slate-500 mt-0.5">
                            Qty: {item.quantity} {item.variant ? `• ${item.variant}` : ''}
                          </p>
                          <p className="text-xs font-bold text-slate-900 mt-1">
                            {formatPrice(item.price)}
                          </p>
                        </div>
                      </div>

                      {/* Item-specific action buttons */}
                      <div className="flex items-center gap-2 self-end sm:self-center shrink-0">
                        {isDelivered && (
                          <>
                            <button
                              onClick={() => onOpenReview(order.id, item)}
                              className="px-3 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold flex items-center gap-1.5 transition-colors"
                            >
                              <Star className="w-3.5 h-3.5 text-amber-500 fill-amber-400" />
                              <span>{item.userRating ? `Reviewed (${item.userRating}★)` : 'Write Review'}</span>
                            </button>
                            <button
                              onClick={() => onOpenReturn(order.id, item)}
                              className="px-3 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold flex items-center gap-1.5 transition-colors"
                            >
                              <RotateCcw className="w-3.5 h-3.5 text-slate-400" />
                              <span>Return / Exchange</span>
                            </button>
                          </>
                        )}
                      </div>
                    </div>
                  ))}
                </div>

                {/* Bottom Footer Actions */}
                <div className="p-4 bg-slate-50/50 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                  <div className="text-xs text-slate-500">
                    Carrier: <span className="font-semibold text-slate-800">{order.carrier}</span> • Tracking: <span className="font-mono text-slate-700">{order.trackingNumber}</span>
                  </div>

                  <div className="flex items-center gap-2">
                    {isProcessing && (
                      <button
                        onClick={() => cancelOrder(order.id)}
                        className="px-3 py-1.5 rounded-xl text-rose-600 hover:bg-rose-50 text-xs font-semibold transition-colors"
                      >
                        Cancel Order
                      </button>
                    )}
                    <button
                      onClick={() => openOrderDetails(order.id)}
                      className="px-3.5 py-1.5 rounded-xl border border-slate-200 hover:bg-white text-xs font-semibold text-slate-700 transition-colors"
                    >
                      View Full Details
                    </button>
                    <button
                      onClick={() => reorderItems(order)}
                      className="px-3.5 py-1.5 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold flex items-center gap-1.5 shadow-xs transition-colors"
                    >
                      <ShoppingBag className="w-3.5 h-3.5" />
                      <span>Buy Again</span>
                    </button>
                  </div>
                </div>
              </div>
            );
          })
        )}
      </div>

    </div>
  );
};
