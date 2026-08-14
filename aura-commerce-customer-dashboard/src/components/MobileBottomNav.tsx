import React from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  LayoutDashboard, 
  Package, 
  Heart, 
  CreditCard, 
  User,
  Sparkles,
  Headphones
} from 'lucide-react';
import { TabType } from '../types/dashboard';

export const MobileBottomNav: React.FC = () => {
  const { 
    activeTab, 
    setActiveTab, 
    orders, 
    wishlist, 
    unreadNotifCount 
  } = useDashboard();

  const activeShipmentsCount = orders.filter(o => o.status === 'out_for_delivery' || o.status === 'shipped' || o.status === 'processing').length;

  const tabs: { id: TabType; label: string; icon: React.ElementType; badge?: number }[] = [
    { id: 'overview', label: 'Home', icon: LayoutDashboard },
    { id: 'orders', label: 'Orders', icon: Package, badge: activeShipmentsCount },
    { id: 'wishlist', label: 'Saved', icon: Heart, badge: wishlist.length },
    { id: 'wallet', label: 'Wallet', icon: CreditCard },
    { id: 'rewards', label: 'Rewards', icon: Sparkles },
    { id: 'profile', label: 'Profile', icon: User }
  ];

  return (
    <nav className="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-lg border-t border-slate-200/90 px-2 py-1.5 safe-area-pb shadow-lg">
      <div className="flex items-center justify-around">
        {tabs.map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.id;

          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`flex flex-col items-center justify-center py-1.5 px-2.5 rounded-xl relative transition-all min-h-[44px] min-w-[48px] ${
                isActive ? 'text-indigo-600 font-bold' : 'text-slate-500 hover:text-slate-800'
              }`}
            >
              <div className="relative">
                <Icon className={`w-5 h-5 transition-transform ${isActive ? 'scale-110 stroke-[2.4]' : 'stroke-[1.8]'}`} />
                {tab.badge !== undefined && tab.badge > 0 && (
                  <span className="absolute -top-1 -right-2 bg-indigo-600 text-white text-[9px] font-bold rounded-full w-4 h-4 flex items-center justify-center ring-2 ring-white">
                    {tab.badge}
                  </span>
                )}
              </div>
              <span className="text-[10px] mt-0.5 tracking-tight font-medium">
                {tab.label}
              </span>
              {isActive && (
                <span className="absolute bottom-0.5 w-1 h-1 bg-indigo-600 rounded-full"></span>
              )}
            </button>
          );
        })}
      </div>
    </nav>
  );
};
