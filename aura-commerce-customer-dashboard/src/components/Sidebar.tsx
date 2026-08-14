import React from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  LayoutDashboard, 
  Package, 
  Heart, 
  MapPin, 
  CreditCard, 
  Award, 
  UserCheck, 
  Headphones,
  Sparkles,
  ChevronRight,
  TrendingUp,
  RotateCcw
} from 'lucide-react';
import { TabType } from '../types/dashboard';

interface NavItem {
  id: TabType;
  label: string;
  icon: React.ElementType;
  badge?: string;
  badgeColor?: string;
}

export const Sidebar: React.FC<{ isOpen?: boolean; onClose?: () => void }> = ({ isOpen, onClose }) => {
  const { 
    activeTab, 
    setActiveTab, 
    orders, 
    wishlist, 
    user, 
    walletBalance, 
    formatPrice,
    resetAllDemoData 
  } = useDashboard();

  const activeShipmentsCount = orders.filter(o => o.status === 'out_for_delivery' || o.status === 'shipped' || o.status === 'processing').length;

  const navItems: NavItem[] = [
    {
      id: 'overview',
      label: 'Dashboard Overview',
      icon: LayoutDashboard
    },
    {
      id: 'orders',
      label: 'Orders & Tracking',
      icon: Package,
      badge: activeShipmentsCount > 0 ? `${activeShipmentsCount} active` : undefined,
      badgeColor: 'bg-indigo-100 text-indigo-700'
    },
    {
      id: 'wishlist',
      label: 'Saved & Collections',
      icon: Heart,
      badge: wishlist.length > 0 ? `${wishlist.length}` : undefined,
      badgeColor: 'bg-rose-100 text-rose-700'
    },
    {
      id: 'addresses',
      label: 'Address Book',
      icon: MapPin
    },
    {
      id: 'wallet',
      label: 'Wallet & Payment',
      icon: CreditCard,
      badge: formatPrice(walletBalance),
      badgeColor: 'bg-emerald-100 text-emerald-800'
    },
    {
      id: 'rewards',
      label: 'VIP Rewards & Perks',
      icon: Award,
      badge: `${user.tierPoints} pts`,
      badgeColor: 'bg-amber-100 text-amber-800'
    },
    {
      id: 'profile',
      label: 'Account & Security',
      icon: UserCheck
    },
    {
      id: 'support',
      label: 'Concierge & Help Desk',
      icon: Headphones
    }
  ];

  const handleSelectTab = (tab: TabType) => {
    setActiveTab(tab);
    if (onClose) onClose();
  };

  // Progress to next tier
  const progressPercent = Math.min(100, Math.round((user.tierPoints / user.nextTierPoints) * 100));

  return (
    <aside className="w-64 shrink-0 flex flex-col justify-between py-6 px-4 bg-white border-r border-slate-200/80 min-h-[calc(100vh-4rem)]">
      {/* Top Nav Items */}
      <div className="space-y-6">
        <div>
          <p className="px-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5">
            Customer Hub
          </p>
          <nav className="space-y-1">
            {navItems.map((item) => {
              const Icon = item.icon;
              const isActive = activeTab === item.id;
              return (
                <button
                  key={item.id}
                  onClick={() => handleSelectTab(item.id)}
                  className={`w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold transition-all group ${
                    isActive
                      ? 'bg-slate-900 text-white shadow-sm shadow-slate-900/10'
                      : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80'
                  }`}
                >
                  <div className="flex items-center gap-3 min-w-0">
                    <Icon className={`w-4 h-4 shrink-0 transition-colors ${
                      isActive ? 'text-indigo-400' : 'text-slate-400 group-hover:text-slate-700'
                    }`} />
                    <span className="truncate">{item.label}</span>
                  </div>
                  {item.badge && (
                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded-md shrink-0 transition-colors ${
                      isActive ? 'bg-slate-800 text-slate-200' : item.badgeColor || 'bg-slate-100 text-slate-700'
                    }`}>
                      {item.badge}
                    </span>
                  )}
                </button>
              );
            })}
          </nav>
        </div>

        {/* Loyalty Tier Progress Card */}
        <div className="p-4 rounded-2xl bg-gradient-to-br from-indigo-950 via-slate-900 to-indigo-900 text-white shadow-lg shadow-indigo-950/20 relative overflow-hidden">
          <div className="absolute -right-6 -bottom-6 w-24 h-24 bg-indigo-500/10 rounded-full blur-xl pointer-events-none"></div>
          <div className="flex items-center justify-between mb-2">
            <div className="flex items-center gap-1.5">
              <Sparkles className="w-3.5 h-3.5 text-amber-300" />
              <span className="text-xs font-bold tracking-wide text-indigo-200 uppercase">
                {user.tier} Status
              </span>
            </div>
            <span className="text-[11px] font-semibold text-amber-300">
              {progressPercent}%
            </span>
          </div>

          <div className="w-full bg-slate-800/80 rounded-full h-1.5 mb-2 overflow-hidden">
            <div 
              className="bg-gradient-to-r from-indigo-400 to-amber-300 h-full rounded-full transition-all duration-500"
              style={{ width: `${progressPercent}%` }}
            ></div>
          </div>

          <p className="text-[11px] text-slate-300 leading-snug">
            <span className="font-bold text-white">{(user.nextTierPoints - user.tierPoints).toLocaleString()} pts</span> until Diamond VIP unlocks Free Same-Day Shipping.
          </p>

          <button
            onClick={() => handleSelectTab('rewards')}
            className="mt-3 w-full py-1.5 px-2.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-[11px] font-semibold flex items-center justify-center gap-1 transition-colors backdrop-blur-sm"
          >
            <span>View Tier Perks</span>
            <ChevronRight className="w-3 h-3" />
          </button>
        </div>
      </div>

      {/* Bottom Profile Quick Summary */}
      <div className="pt-4 border-t border-slate-100 space-y-2">
        <div className="flex items-center gap-3 px-2 py-1">
          <img
            src={user.avatar}
            alt={user.name}
            className="w-9 h-9 rounded-xl object-cover ring-2 ring-slate-100"
          />
          <div className="min-w-0 flex-1">
            <p className="text-xs font-bold text-slate-900 truncate">{user.name}</p>
            <p className="text-[11px] text-slate-400 truncate">Member since {user.memberSince}</p>
          </div>
        </div>
      </div>
    </aside>
  );
};
