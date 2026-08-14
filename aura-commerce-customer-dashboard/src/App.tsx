import React, { useState } from 'react';
import { DashboardProvider, useDashboard } from './context/DashboardContext';
import { Header } from './components/Header';
import { Sidebar } from './components/Sidebar';
import { MobileBottomNav } from './components/MobileBottomNav';
import { CartDrawer } from './components/CartDrawer';
import { QuickCheckoutModal } from './components/QuickCheckoutModal';
import { OrderDetailsModal } from './components/OrderDetailsModal';
import { ReturnRequestModal } from './components/ReturnRequestModal';
import { ReviewModal } from './components/ReviewModal';
import { ToastContainer } from './components/ToastContainer';

// Import Views
import { OverviewView } from './views/OverviewView';
import { OrdersView } from './views/OrdersView';
import { WishlistView } from './views/WishlistView';
import { AddressesView } from './views/AddressesView';
import { WalletView } from './views/WalletView';
import { RewardsView } from './views/RewardsView';
import { ProfileView } from './views/ProfileView';
import { SupportView } from './views/SupportView';

import { OrderItem } from './types/dashboard';

const DashboardMainContent: React.FC = () => {
  const {
    activeTab,
    isCartOpen,
    setIsCartOpen,
    isCheckoutOpen,
    setIsCheckoutOpen,
    selectedOrderId,
    setSelectedOrderId
  } = useDashboard();

  // Return Request Modal state
  const [returnOrderInfo, setReturnOrderInfo] = useState<{
    orderId: string;
    item: OrderItem;
  } | null>(null);

  // Review Modal state
  const [reviewOrderInfo, setReviewOrderInfo] = useState<{
    orderId: string;
    item: OrderItem;
  } | null>(null);

  const handleOpenReturn = (orderId: string, item: OrderItem) => {
    setReturnOrderInfo({ orderId, item });
  };

  const handleOpenReview = (orderId: string, item: OrderItem) => {
    setReviewOrderInfo({ orderId, item });
  };

  return (
    <div className="min-h-screen bg-slate-100/70 text-slate-900 flex flex-col antialiased selection:bg-indigo-500 selection:text-white">
      {/* Top Universal Navigation Header */}
      <Header />

      {/* Main Structural Layout */}
      <div className="flex-1 flex max-w-[1600px] w-full mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-6 gap-6">
        
        {/* Desktop Sidebar (Left rail) */}
        <Sidebar />

        {/* Dynamic Main View Area */}
        <main className="flex-1 min-w-0 pb-20 md:pb-6 overflow-x-hidden">
          {activeTab === 'overview' && <OverviewView />}
          {activeTab === 'orders' && (
            <OrdersView
              onOpenReturn={handleOpenReturn}
              onOpenReview={handleOpenReview}
            />
          )}
          {activeTab === 'wishlist' && <WishlistView />}
          {activeTab === 'addresses' && <AddressesView />}
          {activeTab === 'wallet' && <WalletView />}
          {activeTab === 'rewards' && <RewardsView />}
          {activeTab === 'profile' && <ProfileView />}
          {activeTab === 'support' && <SupportView />}
        </main>
      </div>

      {/* Mobile Bottom Floating Navigation Bar */}
      <MobileBottomNav />

      {/* Global Slide-over Cart Drawer */}
      <CartDrawer
        isOpen={isCartOpen}
        onClose={() => setIsCartOpen(false)}
        onCheckout={() => {
          setIsCartOpen(false);
          setIsCheckoutOpen(true);
        }}
      />

      {/* Quick Checkout & VIP Order Processing Modal */}
      <QuickCheckoutModal
        isOpen={isCheckoutOpen}
        onClose={() => setIsCheckoutOpen(false)}
      />

      {/* Order Details & Full Tracking Timeline Modal */}
      <OrderDetailsModal
        orderId={selectedOrderId}
        onClose={() => setSelectedOrderId(null)}
        onOpenReturn={handleOpenReturn}
        onOpenReview={handleOpenReview}
      />

      {/* Return & Exchange Flow Modal */}
      <ReturnRequestModal
        isOpen={!!returnOrderInfo}
        orderId={returnOrderInfo?.orderId || null}
        item={returnOrderInfo?.item || null}
        onClose={() => setReturnOrderInfo(null)}
      />

      {/* Product Review & Rating Modal */}
      <ReviewModal
        isOpen={!!reviewOrderInfo}
        orderId={reviewOrderInfo?.orderId || null}
        item={reviewOrderInfo?.item || null}
        onClose={() => setReviewOrderInfo(null)}
      />

      {/* Interactive Toast Notifications */}
      <ToastContainer />
    </div>
  );
};

export function App() {
  return (
    <DashboardProvider>
      <DashboardMainContent />
    </DashboardProvider>
  );
}

export default App;
