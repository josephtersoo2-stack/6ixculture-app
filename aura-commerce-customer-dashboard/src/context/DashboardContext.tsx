import React, { createContext, useContext, useState, useEffect } from 'react';
import confetti from 'canvas-confetti';
import {
  TabType,
  UserProfile,
  Order,
  WishlistItem,
  CartItem,
  Address,
  PaymentCard,
  CreditTransaction,
  RewardVoucher,
  NotificationItem,
  SupportTicket,
  SupportMessage,
  LoyaltyTierName
} from '../types/dashboard';
import {
  INITIAL_USER,
  INITIAL_ORDERS,
  INITIAL_WISHLIST,
  INITIAL_ADDRESSES,
  INITIAL_PAYMENT_CARDS,
  INITIAL_TRANSACTIONS,
  INITIAL_VOUCHERS,
  INITIAL_NOTIFICATIONS,
  INITIAL_SUPPORT_TICKETS
} from '../data/mockData';

export interface ToastMessage {
  id: string;
  title: string;
  description?: string;
  type: 'success' | 'info' | 'warning' | 'error';
}

interface DashboardContextType {
  // Navigation
  activeTab: TabType;
  setActiveTab: (tab: TabType) => void;
  searchQuery: string;
  setSearchQuery: (q: string) => void;
  
  // User Profile
  user: UserProfile;
  updateUser: (updates: Partial<UserProfile>) => void;
  
  // Orders
  orders: Order[];
  selectedOrder: Order | null;
  setSelectedOrder: (order: Order | null) => void;
  openOrderDetails: (orderId: string) => void;
  cancelOrder: (orderId: string) => void;
  requestReturn: (orderId: string, itemId: string, reason: string, returnType: string) => void;
  reorderItems: (order: Order) => void;
  addOrderReview: (orderId: string, itemId: string, rating: number, reviewText: string) => void;

  // Wishlist
  wishlist: WishlistItem[];
  wishlistCollections: string[];
  activeWishlistCollection: string;
  setActiveWishlistCollection: (col: string) => void;
  toggleWishlist: (item: Partial<WishlistItem>) => void;
  removeFromWishlist: (id: string) => void;
  moveWishlistToCart: (item: WishlistItem) => void;
  moveAllWishlistToCart: () => void;
  createCollection: (name: string) => void;

  // Cart
  cart: CartItem[];
  isCartOpen: boolean;
  setIsCartOpen: (open: boolean) => void;
  addToCart: (item: {
    productId: string;
    title: string;
    brand: string;
    price: number;
    originalPrice?: number;
    image: string;
    quantity?: number;
    color?: string;
    size?: string;
  }) => void;
  removeFromCart: (id: string) => void;
  updateCartQty: (id: string, qty: number) => void;
  clearCart: () => void;
  appliedPromoCode: string | null;
  applyPromoCode: (code: string) => boolean;
  removePromoCode: () => void;
  promoDiscountPercent: number;
  promoDiscountAmount: number;
  cartSubtotal: number;
  cartTotal: number;
  cartItemsCount: number;
  checkoutCart: (selectedAddressId: string, paymentType: string) => Order;

  // Addresses
  addresses: Address[];
  addAddress: (address: Omit<Address, 'id'>) => void;
  updateAddress: (id: string, address: Partial<Address>) => void;
  deleteAddress: (id: string) => void;
  setDefaultShipping: (id: string) => void;
  setDefaultBilling: (id: string) => void;

  // Wallet & Payments
  paymentCards: PaymentCard[];
  addPaymentCard: (card: Omit<PaymentCard, 'id'>) => void;
  deletePaymentCard: (id: string) => void;
  setDefaultPaymentCard: (id: string) => void;
  walletBalance: number;
  creditTransactions: CreditTransaction[];
  redeemGiftCard: (code: string, amount?: number) => boolean;

  // Rewards & Loyalty
  rewardVouchers: RewardVoucher[];
  redeemRewardVoucher: (voucherId: string) => void;
  referralCode: string;

  // Notifications
  notifications: NotificationItem[];
  unreadNotifCount: number;
  markNotificationAsRead: (id: string) => void;
  markAllNotificationsAsRead: () => void;

  // Support & AI Concierge
  supportTickets: SupportTicket[];
  activeTicketId: string | null;
  setActiveTicketId: (id: string | null) => void;
  sendSupportMessage: (ticketId: string, text: string) => void;
  createSupportTicket: (subject: string, category: SupportTicket['category'], initialMessage: string, orderId?: string) => void;
  liveChatMessages: SupportMessage[];
  sendLiveChatMessage: (text: string) => void;

  // Currencies & Formatting
  currency: 'USD' | 'EUR' | 'GBP' | 'CAD' | 'JPY';
  setCurrency: (c: 'USD' | 'EUR' | 'GBP' | 'CAD' | 'JPY') => void;
  formatPrice: (amountInUSD: number) => string;

  // UI Modals & Toasts
  toasts: ToastMessage[];
  showToast: (title: string, description?: string, type?: 'success' | 'info' | 'warning' | 'error') => void;
  dismissToast: (id: string) => void;
  triggerConfetti: () => void;
  resetAllDemoData: () => void;
}

const DashboardContext = createContext<DashboardContextType | undefined>(undefined);

const CURRENCY_RATES = {
  USD: { symbol: '$', rate: 1.0, decimals: 2 },
  EUR: { symbol: '€', rate: 0.92, decimals: 2 },
  GBP: { symbol: '£', rate: 0.79, decimals: 2 },
  CAD: { symbol: 'CA$', rate: 1.36, decimals: 2 },
  JPY: { symbol: '¥', rate: 154.5, decimals: 0 },
};

export const DashboardProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  // Navigation & Search
  const [activeTab, setActiveTab] = useState<TabType>('overview');
  const [searchQuery, setSearchQuery] = useState('');

  // User Profile
  const [user, setUser] = useState<UserProfile>(() => {
    const saved = localStorage.getItem('aura_user');
    return saved ? JSON.parse(saved) : INITIAL_USER;
  });

  // Orders
  const [orders, setOrders] = useState<Order[]>(() => {
    const saved = localStorage.getItem('aura_orders');
    return saved ? JSON.parse(saved) : INITIAL_ORDERS;
  });
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);

  // Wishlist
  const [wishlist, setWishlist] = useState<WishlistItem[]>(() => {
    const saved = localStorage.getItem('aura_wishlist');
    return saved ? JSON.parse(saved) : INITIAL_WISHLIST;
  });
  const [activeWishlistCollection, setActiveWishlistCollection] = useState<string>('All');
  const [wishlistCollections, setWishlistCollections] = useState<string[]>([
    'All',
    'Favorites',
    'Tech Upgrades',
    'Summer Wardrobe',
    'Home Studio',
    'Gift Ideas'
  ]);

  // Cart
  const [cart, setCart] = useState<CartItem[]>(() => {
    const saved = localStorage.getItem('aura_cart');
    return saved ? JSON.parse(saved) : [
      {
        id: 'cart_demo_1',
        productId: 'prod_audio_1',
        title: 'Aura Studio Wireless ANC Headphones',
        brand: 'Aura Acoustics',
        price: 349.00,
        originalPrice: 399.00,
        image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=600&q=80',
        quantity: 1,
        color: 'Matte Obsidian',
        inStock: true
      }
    ];
  });
  const [isCartOpen, setIsCartOpen] = useState(false);
  const [appliedPromoCode, setAppliedPromoCode] = useState<string | null>(null);
  const [promoDiscountPercent, setPromoDiscountPercent] = useState<number>(0);
  const [promoDiscountAmount, setPromoDiscountAmount] = useState<number>(0);

  // Addresses
  const [addresses, setAddresses] = useState<Address[]>(() => {
    const saved = localStorage.getItem('aura_addresses');
    return saved ? JSON.parse(saved) : INITIAL_ADDRESSES;
  });

  // Payments & Wallet
  const [paymentCards, setPaymentCards] = useState<PaymentCard[]>(() => {
    const saved = localStorage.getItem('aura_payment_cards');
    return saved ? JSON.parse(saved) : INITIAL_PAYMENT_CARDS;
  });
  const [walletBalance, setWalletBalance] = useState<number>(() => {
    const saved = localStorage.getItem('aura_wallet_balance');
    return saved ? parseFloat(saved) : 145.50;
  });
  const [creditTransactions, setCreditTransactions] = useState<CreditTransaction[]>(() => {
    const saved = localStorage.getItem('aura_transactions');
    return saved ? JSON.parse(saved) : INITIAL_TRANSACTIONS;
  });

  // Rewards
  const [rewardVouchers, setRewardVouchers] = useState<RewardVoucher[]>(() => {
    const saved = localStorage.getItem('aura_vouchers');
    return saved ? JSON.parse(saved) : INITIAL_VOUCHERS;
  });

  // Notifications
  const [notifications, setNotifications] = useState<NotificationItem[]>(() => {
    const saved = localStorage.getItem('aura_notifications');
    return saved ? JSON.parse(saved) : INITIAL_NOTIFICATIONS;
  });

  // Support
  const [supportTickets, setSupportTickets] = useState<SupportTicket[]>(() => {
    const saved = localStorage.getItem('aura_support_tickets');
    return saved ? JSON.parse(saved) : INITIAL_SUPPORT_TICKETS;
  });
  const [activeTicketId, setActiveTicketId] = useState<string | null>(null);
  const [liveChatMessages, setLiveChatMessages] = useState<SupportMessage[]>([
    {
      id: 'lmsg_1',
      sender: 'agent',
      text: 'Hello Alexander! 👋 Welcome to Aura Priority Concierge. How can I assist you with your orders, returns, or rewards today?',
      time: 'Just now',
      quickReplies: ['Track Order #AUR-984102', 'Return an Item', 'Loyalty Points Balance', 'Change Delivery Address']
    }
  ]);

  // Currency
  const [currency, setCurrency] = useState<'USD' | 'EUR' | 'GBP' | 'CAD' | 'JPY'>(user.preferredCurrency || 'USD');

  // Toasts
  const [toasts, setToasts] = useState<ToastMessage[]>([]);

  // Sync to LocalStorage
  useEffect(() => {
    localStorage.setItem('aura_user', JSON.stringify(user));
  }, [user]);

  useEffect(() => {
    localStorage.setItem('aura_orders', JSON.stringify(orders));
  }, [orders]);

  useEffect(() => {
    localStorage.setItem('aura_wishlist', JSON.stringify(wishlist));
  }, [wishlist]);

  useEffect(() => {
    localStorage.setItem('aura_cart', JSON.stringify(cart));
  }, [cart]);

  useEffect(() => {
    localStorage.setItem('aura_addresses', JSON.stringify(addresses));
  }, [addresses]);

  useEffect(() => {
    localStorage.setItem('aura_payment_cards', JSON.stringify(paymentCards));
  }, [paymentCards]);

  useEffect(() => {
    localStorage.setItem('aura_wallet_balance', walletBalance.toString());
  }, [walletBalance]);

  useEffect(() => {
    localStorage.setItem('aura_transactions', JSON.stringify(creditTransactions));
  }, [creditTransactions]);

  useEffect(() => {
    localStorage.setItem('aura_vouchers', JSON.stringify(rewardVouchers));
  }, [rewardVouchers]);

  useEffect(() => {
    localStorage.setItem('aura_notifications', JSON.stringify(notifications));
  }, [notifications]);

  useEffect(() => {
    localStorage.setItem('aura_support_tickets', JSON.stringify(supportTickets));
  }, [supportTickets]);

  // Helpers
  const showToast = (title: string, description?: string, type: 'success' | 'info' | 'warning' | 'error' = 'success') => {
    const id = 'toast_' + Date.now() + Math.random().toString(36).substring(2, 6);
    setToasts(prev => [...prev, { id, title, description, type }]);
    setTimeout(() => {
      dismissToast(id);
    }, 4500);
  };

  const dismissToast = (id: string) => {
    setToasts(prev => prev.filter(t => t.id !== id));
  };

  const triggerConfetti = () => {
    try {
      confetti({
        particleCount: 75,
        spread: 60,
        origin: { y: 0.65 },
        colors: ['#4f46e5', '#06b6d4', '#10b981', '#f59e0b', '#ec4899']
      });
    } catch {
      // safe fallback
    }
  };

  const formatPrice = (amountInUSD: number) => {
    const conf = CURRENCY_RATES[currency] || CURRENCY_RATES.USD;
    const converted = amountInUSD * conf.rate;
    return `${conf.symbol}${converted.toLocaleString(undefined, {
      minimumFractionDigits: conf.decimals,
      maximumFractionDigits: conf.decimals
    })}`;
  };

  const updateUser = (updates: Partial<UserProfile>) => {
    setUser(prev => ({ ...prev, ...updates }));
    showToast('Profile Updated', 'Your account settings have been saved successfully.');
  };

  // Orders Actions
  const openOrderDetails = (orderId: string) => {
    const order = orders.find(o => o.id === orderId);
    if (order) {
      setSelectedOrder(order);
    }
  };

  const cancelOrder = (orderId: string) => {
    setOrders(prev => prev.map(o => {
      if (o.id === orderId) {
        return {
          ...o,
          status: 'cancelled' as const,
          notes: 'Order cancelled by customer.'
        };
      }
      return o;
    }));
    showToast('Order Cancelled', `Order #${orderId} has been successfully cancelled and refunded.`, 'info');
  };

  const requestReturn = (orderId: string, itemId: string, reason: string, returnType: string) => {
    setOrders(prev => prev.map(o => {
      if (o.id === orderId) {
        const updatedItems = o.items.map(item => {
          if (item.id === itemId) {
            return { ...item, status: 'returned' as const };
          }
          return item;
        });
        return {
          ...o,
          status: 'returned' as const,
          items: updatedItems
        };
      }
      return o;
    }));

    // Add credit refund
    const targetOrder = orders.find(o => o.id === orderId);
    const targetItem = targetOrder?.items.find(i => i.id === itemId);
    const refundAmt = targetItem ? targetItem.price * targetItem.quantity : 50;

    setWalletBalance(prev => prev + refundAmt);
    setCreditTransactions(prev => [
      {
        id: 'tx_' + Date.now(),
        type: 'refund',
        amount: refundAmt,
        description: `Refund credited for return (${targetItem?.title || 'Item'})`,
        date: 'Today'
      },
      ...prev
    ]);

    triggerConfetti();
    showToast('Return Requested', `Prepaid label generated! $${refundAmt.toFixed(2)} store credit processed.`, 'success');
  };

  const reorderItems = (order: Order) => {
    order.items.forEach(item => {
      addToCart({
        productId: item.productId,
        title: item.title,
        brand: item.brand,
        price: item.price,
        originalPrice: item.originalPrice,
        image: item.image,
        quantity: 1,
        color: item.color,
        size: item.size
      });
    });
    setIsCartOpen(true);
    showToast('Items Added to Cart', `Added ${order.items.length} item(s) from Order #${order.orderNumber}.`, 'success');
  };

  const addOrderReview = (orderId: string, itemId: string, rating: number, reviewText: string) => {
    setOrders(prev => prev.map(o => {
      if (o.id === orderId) {
        return {
          ...o,
          items: o.items.map(it => it.id === itemId ? { ...it, userRating: rating } : it)
        };
      }
      return o;
    }));

    // Award review points!
    setUser(prev => ({
      ...prev,
      tierPoints: prev.tierPoints + 100
    }));

    triggerConfetti();
    showToast('Review Published!', 'Thank you! You earned +100 Loyalty Points.', 'success');
  };

  // Wishlist Actions
  const toggleWishlist = (item: Partial<WishlistItem>) => {
    const exists = wishlist.some(w => w.productId === item.productId || w.id === item.id);
    if (exists) {
      setWishlist(prev => prev.filter(w => w.productId !== item.productId && w.id !== item.id));
      showToast('Removed from Wishlist', `${item.title} removed.`);
    } else {
      const newItem: WishlistItem = {
        id: 'wish_' + Date.now(),
        productId: item.productId || 'prod_' + Date.now(),
        title: item.title || 'Product',
        brand: item.brand || 'Aura Signature',
        price: item.price || 99,
        originalPrice: item.originalPrice,
        inStock: item.inStock ?? true,
        stockCount: item.stockCount ?? 10,
        image: item.image || 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=600&q=80',
        rating: item.rating || 4.9,
        reviewsCount: item.reviewsCount || 42,
        category: item.category || 'General',
        collection: activeWishlistCollection === 'All' ? 'Favorites' : activeWishlistCollection,
        dateAdded: 'Today',
        color: item.color,
        size: item.size
      };
      setWishlist(prev => [newItem, ...prev]);
      showToast('Saved to Wishlist', `${item.title} added to your saved list.`);
    }
  };

  const removeFromWishlist = (id: string) => {
    setWishlist(prev => prev.filter(w => w.id !== id));
    showToast('Item Removed', 'Item removed from your wishlist.', 'info');
  };

  const moveWishlistToCart = (item: WishlistItem) => {
    addToCart({
      productId: item.productId,
      title: item.title,
      brand: item.brand,
      price: item.price,
      originalPrice: item.originalPrice,
      image: item.image,
      quantity: 1,
      color: item.color,
      size: item.size
    });
    removeFromWishlist(item.id);
    setIsCartOpen(true);
    showToast('Moved to Cart', `${item.title} is now in your shopping bag.`);
  };

  const moveAllWishlistToCart = () => {
    const inStockItems = wishlist.filter(w => w.inStock);
    if (inStockItems.length === 0) {
      showToast('No In-Stock Items', 'None of your saved items are currently in stock.', 'warning');
      return;
    }
    inStockItems.forEach(item => {
      addToCart({
        productId: item.productId,
        title: item.title,
        brand: item.brand,
        price: item.price,
        originalPrice: item.originalPrice,
        image: item.image,
        quantity: 1,
        color: item.color,
        size: item.size
      });
    });
    setWishlist(prev => prev.filter(w => !w.inStock));
    setIsCartOpen(true);
    showToast('Items Added to Cart', `Moved ${inStockItems.length} available items to your bag.`);
  };

  const createCollection = (name: string) => {
    if (!name.trim()) return;
    if (wishlistCollections.includes(name.trim())) {
      showToast('Collection exists', 'You already have a collection with this name.', 'warning');
      return;
    }
    setWishlistCollections(prev => [...prev, name.trim()]);
    setActiveWishlistCollection(name.trim());
    showToast('Collection Created', `New collection "${name.trim()}" is ready.`);
  };

  // Cart Actions
  const addToCart = (item: {
    productId: string;
    title: string;
    brand: string;
    price: number;
    originalPrice?: number;
    image: string;
    quantity?: number;
    color?: string;
    size?: string;
  }) => {
    setCart(prev => {
      const existing = prev.find(c => c.productId === item.productId && c.color === item.color && c.size === item.size);
      if (existing) {
        return prev.map(c => c.id === existing.id ? { ...c, quantity: c.quantity + (item.quantity || 1) } : c);
      }
      const newItem: CartItem = {
        id: 'cart_' + Date.now() + Math.random().toString(36).substring(2, 5),
        productId: item.productId,
        title: item.title,
        brand: item.brand,
        price: item.price,
        originalPrice: item.originalPrice,
        image: item.image,
        quantity: item.quantity || 1,
        color: item.color,
        size: item.size,
        inStock: true
      };
      return [newItem, ...prev];
    });
  };

  const removeFromCart = (id: string) => {
    setCart(prev => prev.filter(c => c.id !== id));
  };

  const updateCartQty = (id: string, qty: number) => {
    if (qty <= 0) {
      removeFromCart(id);
      return;
    }
    setCart(prev => prev.map(c => c.id === id ? { ...c, quantity: qty } : c));
  };

  const clearCart = () => {
    setCart([]);
  };

  const applyPromoCode = (code: string): boolean => {
    const cleanCode = code.trim().toUpperCase();
    if (cleanCode === 'VIP-SAVE15-H82' || cleanCode === 'SAVE15') {
      setAppliedPromoCode(cleanCode);
      setPromoDiscountPercent(15);
      setPromoDiscountAmount(0);
      showToast('Promo Code Applied!', '15% discount has been applied to your bag.');
      return true;
    }
    if (cleanCode === 'PLATINUM-35-OFF' || cleanCode === 'SAVE35') {
      setAppliedPromoCode(cleanCode);
      setPromoDiscountAmount(35);
      setPromoDiscountPercent(0);
      showToast('Promo Code Applied!', '$35.00 VIP discount applied to your bag.');
      return true;
    }
    if (cleanCode === 'EXPRESS-FREESHIP' || cleanCode === 'FREESHIP') {
      setAppliedPromoCode(cleanCode);
      setPromoDiscountPercent(0);
      setPromoDiscountAmount(0);
      showToast('Promo Code Applied!', 'Free Express shipping unlocked!');
      return true;
    }
    showToast('Invalid Promo Code', 'Please check the voucher code and try again.', 'error');
    return false;
  };

  const removePromoCode = () => {
    setAppliedPromoCode(null);
    setPromoDiscountPercent(0);
    setPromoDiscountAmount(0);
    showToast('Promo Removed', 'Coupon has been removed.', 'info');
  };

  const cartSubtotal = cart.reduce((sum, it) => sum + (it.price * it.quantity), 0);
  const calculatedDiscount = promoDiscountPercent > 0 
    ? (cartSubtotal * (promoDiscountPercent / 100))
    : promoDiscountAmount > 0 ? Math.min(promoDiscountAmount, cartSubtotal) : 0;
  const cartTotal = Math.max(0, cartSubtotal - calculatedDiscount);
  const cartItemsCount = cart.reduce((count, it) => count + it.quantity, 0);

  const checkoutCart = (selectedAddressId: string, paymentType: string): Order => {
    const shippingAddr = addresses.find(a => a.id === selectedAddressId) || addresses[0];
    const orderNum = 'AUR-' + Math.floor(100000 + Math.random() * 900000);
    const newOrder: Order = {
      id: 'ord_' + Date.now(),
      orderNumber: orderNum,
      date: 'Today, ' + new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
      status: 'processing',
      carrier: 'FedEx Priority Express',
      trackingNumber: 'FX-' + Math.floor(10000000000 + Math.random() * 90000000000),
      estimatedDelivery: 'Estimated in 2-3 Business Days',
      shippingAddress: shippingAddr,
      paymentMethod: {
        cardType: (paymentType as any) || 'visa',
        last4: paymentCards[0]?.last4 || '4288',
        name: 'Default Payment Method'
      },
      subtotal: cartSubtotal,
      shippingFee: 0.00,
      discount: calculatedDiscount,
      tax: Number((cartTotal * 0.0825).toFixed(2)),
      total: Number((cartTotal + cartTotal * 0.0825).toFixed(2)),
      items: cart.map(it => ({
        id: 'item_' + Date.now() + Math.random().toString(36).substring(2, 5),
        productId: it.productId,
        title: it.title,
        brand: it.brand,
        variant: (it.color ? it.color + ' ' : '') + (it.size ? `/ ${it.size}` : ''),
        color: it.color,
        size: it.size,
        price: it.price,
        originalPrice: it.originalPrice,
        quantity: it.quantity,
        image: it.image,
        status: 'active',
        returnEligibleUntil: '30 Days from delivery'
      })),
      trackingTimeline: [
        {
          status: 'processing',
          title: 'Order Confirmed',
          description: 'Payment authorized successfully. Our warehouse is preparing your parcel.',
          timestamp: 'Just now',
          location: 'San Jose Logistics Hub',
          completed: true,
          current: true
        },
        {
          status: 'shipped',
          title: 'Carrier Pickup',
          description: 'FedEx Express manifest assigned.',
          timestamp: 'Pending',
          location: 'San Jose, CA',
          completed: false
        },
        {
          status: 'out_for_delivery',
          title: 'Out for Delivery',
          description: 'Courier on regional route.',
          timestamp: 'Pending',
          location: shippingAddr.city + ', ' + shippingAddr.state,
          completed: false
        },
        {
          status: 'delivered',
          title: 'Delivered',
          description: 'Package delivered to address.',
          timestamp: 'Pending',
          location: shippingAddr.city + ', ' + shippingAddr.state,
          completed: false
        }
      ]
    };

    // Add points for purchase!
    const earnedPoints = Math.floor(newOrder.total * 1.5);
    setUser(prev => ({
      ...prev,
      tierPoints: prev.tierPoints + earnedPoints,
      totalSpent: prev.totalSpent + newOrder.total,
      totalOrdersCount: prev.totalOrdersCount + 1
    }));

    setOrders(prev => [newOrder, ...prev]);
    clearCart();
    setIsCartOpen(false);
    triggerConfetti();

    // Add notification
    setNotifications(prev => [
      {
        id: 'notif_' + Date.now(),
        title: `Order Confirmed: ${newOrder.orderNumber} 🎉`,
        message: `Your order for $${newOrder.total.toFixed(2)} is being prepared. You earned +${earnedPoints} points!`,
        time: 'Just now',
        read: false,
        type: 'order',
        actionTab: 'orders',
        actionId: newOrder.id
      },
      ...prev
    ]);

    showToast('Order Placed Successfully!', `Order #${newOrder.orderNumber} is confirmed. +${earnedPoints} VIP Points earned!`, 'success');
    return newOrder;
  };

  // Addresses Actions
  const addAddress = (address: Omit<Address, 'id'>) => {
    const newAddr: Address = {
      ...address,
      id: 'addr_' + Date.now()
    };
    if (newAddr.isDefaultShipping) {
      setAddresses(prev => prev.map(a => ({ ...a, isDefaultShipping: false })));
    }
    if (newAddr.isDefaultBilling) {
      setAddresses(prev => prev.map(a => ({ ...a, isDefaultBilling: false })));
    }
    setAddresses(prev => [...prev, newAddr]);
    showToast('Address Saved', `${newAddr.label} added to your address book.`);
  };

  const updateAddress = (id: string, updates: Partial<Address>) => {
    setAddresses(prev => prev.map(a => {
      if (a.id === id) {
        return { ...a, ...updates };
      }
      if (updates.isDefaultShipping) {
        return { ...a, isDefaultShipping: false };
      }
      if (updates.isDefaultBilling) {
        return { ...a, isDefaultBilling: false };
      }
      return a;
    }));
    showToast('Address Updated', 'Address details updated successfully.');
  };

  const deleteAddress = (id: string) => {
    setAddresses(prev => prev.filter(a => a.id !== id));
    showToast('Address Deleted', 'Address removed from your address book.', 'info');
  };

  const setDefaultShipping = (id: string) => {
    setAddresses(prev => prev.map(a => ({
      ...a,
      isDefaultShipping: a.id === id
    })));
    showToast('Default Shipping Updated', 'Primary delivery address set.');
  };

  const setDefaultBilling = (id: string) => {
    setAddresses(prev => prev.map(a => ({
      ...a,
      isDefaultBilling: a.id === id
    })));
    showToast('Default Billing Updated', 'Primary billing address set.');
  };

  // Payments & Wallet Actions
  const addPaymentCard = (card: Omit<PaymentCard, 'id'>) => {
    const newCard: PaymentCard = {
      ...card,
      id: 'card_' + Date.now(),
      bgColor: card.cardType === 'visa' 
        ? 'from-slate-900 via-indigo-950 to-slate-900' 
        : card.cardType === 'mastercard' 
        ? 'from-zinc-900 via-stone-800 to-zinc-900' 
        : 'from-emerald-950 via-teal-900 to-slate-900'
    };
    if (newCard.isDefault) {
      setPaymentCards(prev => prev.map(c => ({ ...c, isDefault: false })));
    }
    setPaymentCards(prev => [...prev, newCard]);
    showToast('Payment Method Saved', `Card ending in •••• ${newCard.last4} added securely.`);
  };

  const deletePaymentCard = (id: string) => {
    setPaymentCards(prev => prev.filter(c => c.id !== id));
    showToast('Card Removed', 'Payment method removed.', 'info');
  };

  const setDefaultPaymentCard = (id: string) => {
    setPaymentCards(prev => prev.map(c => ({
      ...c,
      isDefault: c.id === id
    })));
    showToast('Primary Card Updated', 'Default payment method set.');
  };

  const redeemGiftCard = (code: string, amount: number = 50.00): boolean => {
    const clean = code.trim().toUpperCase();
    if (!clean) return false;
    
    setWalletBalance(prev => prev + amount);
    setCreditTransactions(prev => [
      {
        id: 'tx_' + Date.now(),
        type: 'credit_added',
        amount: amount,
        description: `Gift Card Redeemed (${clean})`,
        date: 'Today',
        code: clean
      },
      ...prev
    ]);
    triggerConfetti();
    showToast('Gift Card Redeemed!', `$${amount.toFixed(2)} has been added to your Aura Store Credit.`, 'success');
    return true;
  };

  // Rewards Actions
  const redeemRewardVoucher = (voucherId: string) => {
    const target = rewardVouchers.find(v => v.id === voucherId);
    if (!target) return;

    if (user.tierPoints < target.pointsCost) {
      showToast('Insufficient Points', `You need ${target.pointsCost} points for this reward.`, 'warning');
      return;
    }

    setUser(prev => ({
      ...prev,
      tierPoints: prev.tierPoints - target.pointsCost
    }));

    setRewardVouchers(prev => prev.map(v => v.id === voucherId ? { ...v, redeemedDate: 'Today' } : v));
    
    // Copy code automatically or show toast
    try {
      navigator.clipboard.writeText(target.code);
    } catch {
      // safe fallback
    }

    triggerConfetti();
    showToast('Voucher Claimed!', `Code "${target.code}" copied to your clipboard. Use it at checkout!`, 'success');
  };

  const referralCode = 'AURA-ALEX-VIP';

  // Notifications Actions
  const unreadNotifCount = notifications.filter(n => !n.read).length;
  
  const markNotificationAsRead = (id: string) => {
    setNotifications(prev => prev.map(n => n.id === id ? { ...n, read: true } : n));
  };

  const markAllNotificationsAsRead = () => {
    setNotifications(prev => prev.map(n => ({ ...n, read: true })));
    showToast('All caught up', 'All notifications marked as read.');
  };

  // Support Actions
  const sendSupportMessage = (ticketId: string, text: string) => {
    const newMsg: SupportMessage = {
      id: 'msg_' + Date.now(),
      sender: 'user',
      text,
      time: 'Just now'
    };

    setSupportTickets(prev => prev.map(t => {
      if (t.id === ticketId) {
        return {
          ...t,
          lastUpdated: 'Just now',
          messages: [...t.messages, newMsg]
        };
      }
      return t;
    }));

    // Auto agent reply after 1.2 seconds
    setTimeout(() => {
      const agentReply: SupportMessage = {
        id: 'msg_rep_' + Date.now(),
        sender: 'agent',
        text: `Thank you for your message, Alexander. A dedicated Aura Concierge agent has received your request regarding Ticket #${ticketId} and will verify details promptly.`,
        time: 'Just now'
      };
      setSupportTickets(prev => prev.map(t => {
        if (t.id === ticketId) {
          return {
            ...t,
            lastUpdated: 'Just now',
            messages: [...t.messages, agentReply]
          };
        }
        return t;
      }));
    }, 1200);
  };

  const createSupportTicket = (subject: string, category: SupportTicket['category'], initialMessage: string, orderId?: string) => {
    const newTicket: SupportTicket = {
      id: 'tkt_' + Date.now(),
      ticketNumber: 'TKT-' + Math.floor(100000 + Math.random() * 900000),
      subject,
      orderId,
      category,
      status: 'open',
      createdAt: 'Today',
      lastUpdated: 'Just now',
      messages: [
        {
          id: 'msg_init_' + Date.now(),
          sender: 'user',
          text: initialMessage,
          time: 'Just now'
        },
        {
          id: 'msg_bot_' + Date.now(),
          sender: 'agent',
          text: `Hello Alexander! We have opened ticket #${subject} under ${category}. Our priority desk is reviewing your order history and will update you shortly.`,
          time: 'Just now'
        }
      ]
    };
    setSupportTickets(prev => [newTicket, ...prev]);
    setActiveTicketId(newTicket.id);
    showToast('Support Ticket Created', `Ticket #${newTicket.ticketNumber} is active.`);
  };

  const sendLiveChatMessage = (text: string) => {
    const userMsg: SupportMessage = {
      id: 'lmsg_' + Date.now(),
      sender: 'user',
      text,
      time: 'Just now'
    };
    setLiveChatMessages(prev => [...prev, userMsg]);

    // Simulated Smart AI Concierge
    setTimeout(() => {
      let botResponse = "I'm right here to assist you with anything regarding your Aura orders, warranties, or delivery updates!";
      let quickReps: string[] = ['Check order status', 'Start a return', 'Redeem points'];

      const lower = text.toLowerCase();
      if (lower.includes('track') || lower.includes('order') || lower.includes('9841') || lower.includes('where')) {
        botResponse = "Your Order #AUR-984102 is currently on FedEx truck with driver Mark and scheduled for delivery Today by 4:30 PM at 742 Evergreen Terrace Apt 4B. Signature is required.";
        quickReps = ['View Full Live Map', 'Change Delivery Notes', 'Contact FedEx'];
      } else if (lower.includes('return') || lower.includes('refund') || lower.includes('exchange')) {
        botResponse = "You can initiate free returns on any order delivered within the last 30 days! Simply click 'Request Return' on any eligible item in your Orders tab to get an instant prepaid QR code & shipping label.";
        quickReps = ['Go to Orders tab', 'View Return Policy'];
      } else if (lower.includes('points') || lower.includes('reward') || lower.includes('tier') || lower.includes('platinum')) {
        botResponse = `You currently have ${user.tierPoints} VIP Points (${user.tier} Tier). You are just ${user.nextTierPoints - user.tierPoints} points away from unlocking Diamond Tier VIP benefits!`;
        quickReps = ['Browse Rewards Catalog', 'View Tier Benefits'];
      } else if (lower.includes('address') || lower.includes('buzzer') || lower.includes('gate')) {
        botResponse = "You have 3 saved addresses in your Address Book. Your primary default address is 742 Evergreen Terrace Apt 4B. You can update buzzer instructions or add a new delivery location anytime in the Addresses tab.";
        quickReps = ['Manage Addresses', 'Add New Address'];
      }

      setLiveChatMessages(prev => [
        ...prev,
        {
          id: 'lmsg_rep_' + Date.now(),
          sender: 'agent',
          text: botResponse,
          time: 'Just now',
          quickReplies: quickReps
        }
      ]);
    }, 900);
  };

  const resetAllDemoData = () => {
    localStorage.removeItem('aura_user');
    localStorage.removeItem('aura_orders');
    localStorage.removeItem('aura_wishlist');
    localStorage.removeItem('aura_cart');
    localStorage.removeItem('aura_addresses');
    localStorage.removeItem('aura_payment_cards');
    localStorage.removeItem('aura_wallet_balance');
    localStorage.removeItem('aura_transactions');
    localStorage.removeItem('aura_vouchers');
    localStorage.removeItem('aura_notifications');
    localStorage.removeItem('aura_support_tickets');

    setUser(INITIAL_USER);
    setOrders(INITIAL_ORDERS);
    setWishlist(INITIAL_WISHLIST);
    setAddresses(INITIAL_ADDRESSES);
    setPaymentCards(INITIAL_PAYMENT_CARDS);
    setWalletBalance(145.50);
    setCreditTransactions(INITIAL_TRANSACTIONS);
    setRewardVouchers(INITIAL_VOUCHERS);
    setNotifications(INITIAL_NOTIFICATIONS);
    setSupportTickets(INITIAL_SUPPORT_TICKETS);
    setCart([]);
    showToast('Demo State Reset', 'Restored default sample e-commerce data.');
  };

  return (
    <DashboardContext.Provider
      value={{
        activeTab,
        setActiveTab,
        searchQuery,
        setSearchQuery,
        user,
        updateUser,
        orders,
        selectedOrder,
        setSelectedOrder,
        openOrderDetails,
        cancelOrder,
        requestReturn,
        reorderItems,
        addOrderReview,
        wishlist,
        wishlistCollections,
        activeWishlistCollection,
        setActiveWishlistCollection,
        toggleWishlist,
        removeFromWishlist,
        moveWishlistToCart,
        moveAllWishlistToCart,
        createCollection,
        cart,
        isCartOpen,
        setIsCartOpen,
        addToCart,
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
        checkoutCart,
        addresses,
        addAddress,
        updateAddress,
        deleteAddress,
        setDefaultShipping,
        setDefaultBilling,
        paymentCards,
        addPaymentCard,
        deletePaymentCard,
        setDefaultPaymentCard,
        walletBalance,
        creditTransactions,
        redeemGiftCard,
        rewardVouchers,
        redeemRewardVoucher,
        referralCode,
        notifications,
        unreadNotifCount,
        markNotificationAsRead,
        markAllNotificationsAsRead,
        supportTickets,
        activeTicketId,
        setActiveTicketId,
        sendSupportMessage,
        createSupportTicket,
        liveChatMessages,
        sendLiveChatMessage,
        currency,
        setCurrency,
        formatPrice,
        toasts,
        showToast,
        dismissToast,
        triggerConfetti,
        resetAllDemoData
      }}
    >
      {children}
    </DashboardContext.Provider>
  );
};

export const useDashboard = () => {
  const context = useContext(DashboardContext);
  if (!context) {
    throw new Error('useDashboard must be used within a DashboardProvider');
  }
  return context;
};
