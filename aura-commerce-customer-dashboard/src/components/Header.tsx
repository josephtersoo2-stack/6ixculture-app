import React, { useState, useRef, useEffect } from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  Search, 
  ShoppingBag, 
  Bell, 
  Sparkles, 
  ChevronDown, 
  CheckCheck, 
  Package, 
  Tag, 
  ShieldCheck, 
  RotateCcw,
  Menu,
  X,
  ExternalLink,
  Compass,
  ArrowRight
} from 'lucide-react';
import { TabType } from '../types/dashboard';

export const Header: React.FC<{ onOpenMobileMenu?: () => void }> = ({ onOpenMobileMenu }) => {
  const {
    user,
    activeTab,
    setActiveTab,
    cartItemsCount,
    setIsCartOpen,
    notifications,
    unreadNotifCount,
    markNotificationAsRead,
    markAllNotificationsAsRead,
    currency,
    setCurrency,
    formatPrice,
    searchQuery,
    setSearchQuery,
    orders,
    wishlist,
    openOrderDetails,
    resetAllDemoData
  } = useDashboard();

  const [isNotifOpen, setIsNotifOpen] = useState(false);
  const [isUserMenuOpen, setIsUserMenuOpen] = useState(false);
  const [isSearchFocused, setIsSearchFocused] = useState(false);

  const notifRef = useRef<HTMLDivElement>(null);
  const userMenuRef = useRef<HTMLDivElement>(null);
  const searchRef = useRef<HTMLDivElement>(null);

  // Close dropdowns on outside click
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (notifRef.current && !notifRef.current.contains(e.target as Node)) {
        setIsNotifOpen(false);
      }
      if (userMenuRef.current && !userMenuRef.current.contains(e.target as Node)) {
        setIsUserMenuOpen(false);
      }
      if (searchRef.current && !searchRef.current.contains(e.target as Node)) {
        setIsSearchFocused(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Filter search results
  const trimmedSearch = searchQuery.trim().toLowerCase();
  const searchOrderResults = trimmedSearch.length > 1
    ? orders.filter(o => 
        o.orderNumber.toLowerCase().includes(trimmedSearch) ||
        o.carrier.toLowerCase().includes(trimmedSearch) ||
        o.items.some(it => it.title.toLowerCase().includes(trimmedSearch) || it.brand.toLowerCase().includes(trimmedSearch))
      ).slice(0, 3)
    : [];

  const searchWishlistResults = trimmedSearch.length > 1
    ? wishlist.filter(w => 
        w.title.toLowerCase().includes(trimmedSearch) ||
        w.brand.toLowerCase().includes(trimmedSearch) ||
        w.category.toLowerCase().includes(trimmedSearch)
      ).slice(0, 3)
    : [];

  const handleSelectSearchResult = (type: 'order' | 'wishlist', id: string) => {
    setIsSearchFocused(false);
    setSearchQuery('');
    if (type === 'order') {
      setActiveTab('orders');
      openOrderDetails(id);
    } else {
      setActiveTab('wishlist');
    }
  };

  return (
    <header className="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-slate-200/80 transition-all">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16 gap-3">
          
          {/* Brand & Mobile Hamburger */}
          <div className="flex items-center gap-3">
            <button
              onClick={onOpenMobileMenu}
              className="md:hidden p-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors"
              aria-label="Open Navigation Menu"
            >
              <Menu className="w-5 h-5" />
            </button>

            <div 
              onClick={() => setActiveTab('overview')}
              className="flex items-center gap-2.5 cursor-pointer group select-none"
            >
              <div className="w-9 h-9 rounded-xl bg-gradient-to-tr from-slate-900 via-indigo-950 to-indigo-700 flex items-center justify-center text-white shadow-md shadow-indigo-950/20 group-hover:scale-105 transition-transform">
                <Sparkles className="w-4.5 h-4.5 text-indigo-300" />
              </div>
              <div className="flex flex-col">
                <span className="font-display font-bold text-lg text-slate-900 tracking-tight flex items-center gap-1.5 leading-none">
                  Aura
                  <span className="text-[10px] font-semibold tracking-wider uppercase px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-100/80">
                    Account
                  </span>
                </span>
                <span className="text-[11px] text-slate-400 font-medium hidden sm:inline">
                  Client VIP Portal
                </span>
              </div>
            </div>
          </div>

          {/* Center Search Bar */}
          <div className="hidden md:flex flex-1 max-w-md mx-4 relative" ref={searchRef}>
            <div className={`w-full flex items-center gap-2.5 px-3.5 py-2 rounded-xl bg-slate-100/90 border transition-all ${
              isSearchFocused ? 'bg-white border-indigo-500 ring-4 ring-indigo-500/10 shadow-sm' : 'border-slate-200/80 hover:border-slate-300'
            }`}>
              <Search className="w-4 h-4 text-slate-400 shrink-0" />
              <input
                type="text"
                placeholder="Search orders, items, tracking numbers, or help..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                onFocus={() => setIsSearchFocused(true)}
                className="w-full bg-transparent text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-none"
              />
              {searchQuery && (
                <button 
                  onClick={() => setSearchQuery('')}
                  className="text-slate-400 hover:text-slate-600 p-0.5"
                >
                  <X className="w-3.5 h-3.5" />
                </button>
              )}
            </div>

            {/* Live Search Dropdown */}
            {isSearchFocused && trimmedSearch.length > 1 && (
              <div className="absolute top-full left-0 right-0 mt-2 bg-white rounded-2xl shadow-xl border border-slate-200 p-3 z-50 animate-in fade-in zoom-in-95 duration-150">
                {searchOrderResults.length === 0 && searchWishlistResults.length === 0 ? (
                  <div className="p-4 text-center text-slate-500 text-xs">
                    No matching orders or saved items found for "<span className="font-semibold text-slate-700">{searchQuery}</span>"
                  </div>
                ) : (
                  <div className="space-y-3">
                    {searchOrderResults.length > 0 && (
                      <div>
                        <p className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider px-2 mb-1.5">
                          Matching Orders ({searchOrderResults.length})
                        </p>
                        <div className="space-y-1">
                          {searchOrderResults.map(order => (
                            <button
                              key={order.id}
                              onClick={() => handleSelectSearchResult('order', order.id)}
                              className="w-full flex items-center justify-between p-2 rounded-xl hover:bg-slate-50 text-left transition-colors group"
                            >
                              <div className="flex items-center gap-2.5 min-w-0">
                                <div className="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                                  <Package className="w-4 h-4" />
                                </div>
                                <div className="min-w-0">
                                  <p className="text-xs font-semibold text-slate-900 truncate">
                                    Order #{order.orderNumber} • {order.items[0]?.title}
                                  </p>
                                  <p className="text-[11px] text-slate-500">
                                    {order.date} • {order.status.replace('_', ' ')}
                                  </p>
                                </div>
                              </div>
                              <span className="text-xs font-semibold text-slate-900 shrink-0">
                                {formatPrice(order.total)}
                              </span>
                            </button>
                          ))}
                        </div>
                      </div>
                    )}

                    {searchWishlistResults.length > 0 && (
                      <div className="pt-2 border-t border-slate-100">
                        <p className="text-[11px] font-semibold text-slate-400 uppercase tracking-wider px-2 mb-1.5">
                          Saved Wishlist Items ({searchWishlistResults.length})
                        </p>
                        <div className="space-y-1">
                          {searchWishlistResults.map(item => (
                            <button
                              key={item.id}
                              onClick={() => handleSelectSearchResult('wishlist', item.id)}
                              className="w-full flex items-center justify-between p-2 rounded-xl hover:bg-slate-50 text-left transition-colors"
                            >
                              <div className="flex items-center gap-2.5 min-w-0">
                                <img 
                                  src={item.image} 
                                  alt={item.title} 
                                  className="w-8 h-8 rounded-lg object-cover shrink-0" 
                                />
                                <div className="min-w-0">
                                  <p className="text-xs font-semibold text-slate-900 truncate">
                                    {item.title}
                                  </p>
                                  <p className="text-[11px] text-slate-500">
                                    {item.brand} • {item.inStock ? 'In Stock' : 'Out of Stock'}
                                  </p>
                                </div>
                              </div>
                              <span className="text-xs font-semibold text-indigo-600 shrink-0">
                                {formatPrice(item.price)}
                              </span>
                            </button>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Right Action Tools: Currency, Notifications, Cart, User Dropdown */}
          <div className="flex items-center gap-1.5 sm:gap-2.5">
            
            {/* Currency Selector */}
            <div className="relative">
              <select
                value={currency}
                onChange={(e) => setCurrency(e.target.value as any)}
                className="appearance-none bg-slate-100/80 hover:bg-slate-200/70 border border-slate-200/80 text-slate-700 text-xs font-semibold py-1.5 pl-2.5 pr-6 rounded-xl cursor-pointer focus:outline-none transition-colors"
              >
                <option value="USD">USD ($)</option>
                <option value="EUR">EUR (€)</option>
                <option value="GBP">GBP (£)</option>
                <option value="CAD">CAD ($)</option>
                <option value="JPY">JPY (¥)</option>
              </select>
              <ChevronDown className="w-3 h-3 text-slate-400 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" />
            </div>

            {/* Notifications Dropdown */}
            <div className="relative" ref={notifRef}>
              <button
                onClick={() => setIsNotifOpen(!isNotifOpen)}
                className="relative p-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors"
                aria-label="Notifications"
              >
                <Bell className="w-5 h-5" />
                {unreadNotifCount > 0 && (
                  <span className="absolute top-1 right-1 w-4 h-4 bg-indigo-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center ring-2 ring-white animate-pulse">
                    {unreadNotifCount}
                  </span>
                )}
              </button>

              {isNotifOpen && (
                <div className="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl shadow-xl border border-slate-200/90 py-3 z-50 animate-in fade-in zoom-in-95 duration-150">
                  <div className="flex items-center justify-between px-4 pb-2.5 border-b border-slate-100">
                    <div className="flex items-center gap-2">
                      <span className="font-semibold text-slate-900 text-sm">Notifications</span>
                      {unreadNotifCount > 0 && (
                        <span className="px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-bold">
                          {unreadNotifCount} new
                        </span>
                      )}
                    </div>
                    {unreadNotifCount > 0 && (
                      <button
                        onClick={markAllNotificationsAsRead}
                        className="text-xs text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1 transition-colors"
                      >
                        <CheckCheck className="w-3.5 h-3.5" />
                        Mark all read
                      </button>
                    )}
                  </div>

                  <div className="max-h-80 overflow-y-auto divide-y divide-slate-100">
                    {notifications.length === 0 ? (
                      <p className="text-center py-6 text-xs text-slate-400">No notifications yet</p>
                    ) : (
                      notifications.map(notif => (
                        <div
                          key={notif.id}
                          onClick={() => {
                            markNotificationAsRead(notif.id);
                            if (notif.actionTab) {
                              setActiveTab(notif.actionTab);
                              if (notif.actionId && notif.actionTab === 'orders') {
                                openOrderDetails(notif.actionId);
                              }
                            }
                            setIsNotifOpen(false);
                          }}
                          className={`p-3.5 hover:bg-slate-50 cursor-pointer transition-colors flex items-start gap-3 ${
                            !notif.read ? 'bg-indigo-50/40' : ''
                          }`}
                        >
                          <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5 ${
                            notif.type === 'order' ? 'bg-sky-100 text-sky-700' :
                            notif.type === 'deal' ? 'bg-amber-100 text-amber-700' :
                            notif.type === 'reward' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700'
                          }`}>
                            {notif.type === 'order' && <Package className="w-4 h-4" />}
                            {notif.type === 'deal' && <Tag className="w-4 h-4" />}
                            {notif.type === 'reward' && <Sparkles className="w-4 h-4" />}
                            {notif.type === 'security' && <ShieldCheck className="w-4 h-4" />}
                          </div>
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center justify-between gap-1">
                              <p className={`text-xs font-semibold ${!notif.read ? 'text-slate-900' : 'text-slate-700'}`}>
                                {notif.title}
                              </p>
                              <span className="text-[10px] text-slate-400 shrink-0">{notif.time}</span>
                            </div>
                            <p className="text-[11px] text-slate-500 mt-0.5 leading-relaxed line-clamp-2">
                              {notif.message}
                            </p>
                          </div>
                        </div>
                      ))
                    )}
                  </div>
                </div>
              )}
            </div>

            {/* Shopping Bag / Cart Drawer Button */}
            <button
              onClick={() => setIsCartOpen(true)}
              className="relative p-2 rounded-xl text-slate-700 hover:text-slate-900 hover:bg-slate-100 transition-colors flex items-center gap-2 group"
              aria-label="Shopping Cart"
            >
              <div className="relative">
                <ShoppingBag className="w-5 h-5 text-slate-700 group-hover:scale-110 transition-transform" />
                {cartItemsCount > 0 && (
                  <span className="absolute -top-1 -right-1 w-4 h-4 bg-slate-900 text-white text-[10px] font-bold rounded-full flex items-center justify-center ring-2 ring-white">
                    {cartItemsCount}
                  </span>
                )}
              </div>
              <span className="hidden lg:inline text-xs font-semibold text-slate-700">
                Bag
              </span>
            </button>

            {/* User Profile Avatar & Dropdown */}
            <div className="relative ml-1" ref={userMenuRef}>
              <button
                onClick={() => setIsUserMenuOpen(!isUserMenuOpen)}
                className="flex items-center gap-2 p-1 pl-1.5 rounded-xl hover:bg-slate-100 transition-colors border border-transparent hover:border-slate-200"
              >
                <div className="relative">
                  <img
                    src={user.avatar}
                    alt={user.name}
                    className="w-8 h-8 rounded-lg object-cover ring-1 ring-slate-200"
                  />
                  <span className="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-emerald-500 border-2 border-white rounded-full"></span>
                </div>
                <div className="hidden sm:flex flex-col text-left">
                  <span className="text-xs font-bold text-slate-900 leading-tight truncate max-w-[100px]">
                    {user.firstName}
                  </span>
                  <span className="text-[10px] font-semibold text-indigo-600 leading-tight">
                    {user.tier} VIP
                  </span>
                </div>
                <ChevronDown className="w-3.5 h-3.5 text-slate-400 hidden sm:block" />
              </button>

              {isUserMenuOpen && (
                <div className="absolute right-0 mt-2 w-64 bg-white rounded-2xl shadow-xl border border-slate-200/90 py-2 z-50 animate-in fade-in zoom-in-95 duration-150">
                  <div className="px-4 py-3 border-b border-slate-100">
                    <p className="text-xs font-bold text-slate-900">{user.name}</p>
                    <p className="text-[11px] text-slate-500 truncate">{user.email}</p>
                    <div className="mt-2.5 flex items-center justify-between bg-indigo-50/80 p-2 rounded-xl border border-indigo-100">
                      <div>
                        <p className="text-[10px] font-semibold text-indigo-700 uppercase tracking-wider">
                          {user.tier} Tier
                        </p>
                        <p className="text-xs font-bold text-indigo-950">
                          {user.tierPoints.toLocaleString()} Points
                        </p>
                      </div>
                      <button
                        onClick={() => {
                          setActiveTab('rewards');
                          setIsUserMenuOpen(false);
                        }}
                        className="text-[10px] font-semibold bg-indigo-600 text-white px-2 py-1 rounded-lg hover:bg-indigo-700 transition-colors"
                      >
                        Redeem
                      </button>
                    </div>
                  </div>

                  <div className="py-1">
                    <button
                      onClick={() => {
                        setActiveTab('profile');
                        setIsUserMenuOpen(false);
                      }}
                      className="w-full px-4 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50 flex items-center justify-between"
                    >
                      Account Settings
                      <ArrowRight className="w-3.5 h-3.5 text-slate-400" />
                    </button>
                    <button
                      onClick={() => {
                        setActiveTab('wallet');
                        setIsUserMenuOpen(false);
                      }}
                      className="w-full px-4 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50 flex items-center justify-between"
                    >
                      Store Credits & Wallet
                      <span className="text-[11px] font-semibold text-emerald-600">
                        {formatPrice(145.50)}
                      </span>
                    </button>
                    <button
                      onClick={() => {
                        setActiveTab('support');
                        setIsUserMenuOpen(false);
                      }}
                      className="w-full px-4 py-2 text-left text-xs font-medium text-slate-700 hover:bg-slate-50 flex items-center justify-between"
                    >
                      24/7 VIP Concierge Support
                      <span className="w-2 h-2 rounded-full bg-emerald-500"></span>
                    </button>
                  </div>

                  <div className="pt-1 border-t border-slate-100">
                    <button
                      onClick={() => {
                        resetAllDemoData();
                        setIsUserMenuOpen(false);
                      }}
                      className="w-full px-4 py-2 text-left text-xs font-medium text-rose-600 hover:bg-rose-50 flex items-center gap-2 transition-colors"
                    >
                      <RotateCcw className="w-3.5 h-3.5" />
                      Reset Demo State
                    </button>
                  </div>
                </div>
              )}
            </div>

          </div>

        </div>
      </div>
    </header>
  );
};
