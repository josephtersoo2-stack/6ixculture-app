export type TabType = 
  | 'overview' 
  | 'orders' 
  | 'wishlist' 
  | 'addresses' 
  | 'wallet' 
  | 'rewards' 
  | 'profile' 
  | 'support';

export type LoyaltyTierName = 'Silver' | 'Gold' | 'Platinum' | 'Diamond';

export interface UserProfile {
  id: string;
  name: string;
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  avatar: string;
  memberSince: string;
  tier: LoyaltyTierName;
  tierPoints: number;
  nextTierPoints: number;
  totalSpent: number;
  totalOrdersCount: number;
  preferredCurrency: 'USD' | 'EUR' | 'GBP' | 'CAD' | 'JPY';
  preferredLanguage: string;
  genderPreference: string;
  sizePreferences: {
    tops: string;
    bottoms: string;
    shoes: string;
  };
  twoFactorEnabled: boolean;
  marketingEmails: boolean;
  smsAlerts: boolean;
  orderUpdatesEmail: boolean;
  priceDropAlerts: boolean;
}

export interface OrderItem {
  id: string;
  productId: string;
  title: string;
  brand: string;
  variant?: string;
  color?: string;
  size?: string;
  price: number;
  originalPrice?: number;
  quantity: number;
  image: string;
  status: 'active' | 'delivered' | 'returned' | 'cancelled';
  userRating?: number;
  returnEligibleUntil: string;
}

export type OrderStatus = 'processing' | 'shipped' | 'out_for_delivery' | 'delivered' | 'cancelled' | 'returned';

export interface TrackingCheckpoint {
  status: OrderStatus;
  title: string;
  description: string;
  timestamp: string;
  location: string;
  completed: boolean;
  current?: boolean;
}

export interface Address {
  id: string;
  label: string; // e.g. "Home", "Office", "Beach House"
  recipientName: string;
  street: string;
  apartment?: string;
  city: string;
  state: string;
  zipCode: string;
  country: string;
  phone: string;
  isDefaultShipping: boolean;
  isDefaultBilling: boolean;
  deliveryInstructions?: string;
}

export interface PaymentCard {
  id: string;
  cardType: 'visa' | 'mastercard' | 'amex' | 'apple_pay';
  cardholderName: string;
  last4: string;
  expMonth: string;
  expYear: string;
  isDefault: boolean;
  bgColor?: string;
}

export interface Order {
  id: string;
  orderNumber: string;
  date: string;
  status: OrderStatus;
  carrier: string; // 'FedEx Priority', 'DHL Express', 'UPS Next Day'
  trackingNumber: string;
  estimatedDelivery: string;
  deliveredDate?: string;
  items: OrderItem[];
  subtotal: number;
  shippingFee: number;
  discount: number;
  tax: number;
  total: number;
  shippingAddress: Address;
  paymentMethod: {
    cardType: 'visa' | 'mastercard' | 'amex' | 'apple_pay' | 'store_credit';
    last4?: string;
    name?: string;
  };
  trackingTimeline: TrackingCheckpoint[];
  notes?: string;
}

export interface WishlistItem {
  id: string;
  productId: string;
  title: string;
  brand: string;
  price: number;
  originalPrice?: number;
  inStock: boolean;
  stockCount: number;
  image: string;
  rating: number;
  reviewsCount: number;
  category: string;
  collection: string; // 'Favorites', 'Tech Upgrades', 'Summer Wardrobe', 'Home Studio'
  dateAdded: string;
  color?: string;
  size?: string;
  priceDropped?: boolean;
  previousPrice?: number;
}

export interface CartItem {
  id: string;
  productId: string;
  title: string;
  brand: string;
  price: number;
  originalPrice?: number;
  image: string;
  quantity: number;
  color?: string;
  size?: string;
  inStock: boolean;
}

export interface CreditTransaction {
  id: string;
  type: 'credit_added' | 'order_redemption' | 'refund' | 'referral_bonus' | 'points_redemption';
  amount: number;
  description: string;
  date: string;
  code?: string;
}

export interface RewardVoucher {
  id: string;
  title: string;
  discountText: string;
  minSpend: number;
  pointsCost: number;
  code: string;
  expiresAt: string;
  category: 'discount' | 'shipping' | 'gift' | 'vip';
  unlocked: boolean;
  redeemedDate?: string;
}

export interface NotificationItem {
  id: string;
  title: string;
  message: string;
  time: string;
  read: boolean;
  type: 'order' | 'deal' | 'security' | 'reward';
  actionTab?: TabType;
  actionId?: string;
}

export interface SupportMessage {
  id: string;
  sender: 'user' | 'agent' | 'system';
  text: string;
  time: string;
  quickReplies?: string[];
}

export interface SupportTicket {
  id: string;
  ticketNumber: string;
  subject: string;
  orderId?: string;
  category: 'Shipping & Delivery' | 'Returns & Refunds' | 'Payment & Billing' | 'Product Question' | 'Account Security';
  status: 'open' | 'in_progress' | 'resolved';
  lastUpdated: string;
  createdAt: string;
  messages: SupportMessage[];
}

export interface ProductRecommendation {
  id: string;
  title: string;
  brand: string;
  price: number;
  originalPrice?: number;
  image: string;
  rating: number;
  reviewsCount: number;
  badge?: string;
  category: string;
}
