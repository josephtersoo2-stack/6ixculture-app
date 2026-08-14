import { 
  UserProfile, 
  Order, 
  WishlistItem, 
  Address, 
  PaymentCard, 
  CreditTransaction, 
  RewardVoucher, 
  NotificationItem, 
  SupportTicket, 
  ProductRecommendation 
} from '../types/dashboard';

export const INITIAL_USER: UserProfile = {
  id: 'usr_849204',
  name: 'Alexander Hayes',
  firstName: 'Alexander',
  lastName: 'Hayes',
  email: 'alex.hayes@example.com',
  phone: '+1 (555) 389-2041',
  avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80',
  memberSince: 'March 2023',
  tier: 'Platinum',
  tierPoints: 3450,
  nextTierPoints: 5000,
  totalSpent: 4280.50,
  totalOrdersCount: 14,
  preferredCurrency: 'USD',
  preferredLanguage: 'English (US)',
  genderPreference: 'Men / Unisex',
  sizePreferences: {
    tops: 'Medium',
    bottoms: '32 / 32',
    shoes: 'US 10.5'
  },
  twoFactorEnabled: true,
  marketingEmails: true,
  smsAlerts: true,
  orderUpdatesEmail: true,
  priceDropAlerts: true,
};

export const INITIAL_ADDRESSES: Address[] = [
  {
    id: 'addr_1',
    label: 'Primary Residence',
    recipientName: 'Alexander Hayes',
    street: '742 Evergreen Terrace',
    apartment: 'Apt 4B',
    city: 'San Francisco',
    state: 'CA',
    zipCode: '94107',
    country: 'United States',
    phone: '+1 (555) 389-2041',
    isDefaultShipping: true,
    isDefaultBilling: true,
    deliveryInstructions: 'Ring buzzer #402. Leave with building concierge if unavailable.'
  },
  {
    id: 'addr_2',
    label: 'Design Studio & Office',
    recipientName: 'Alexander Hayes (Studio)',
    street: '550 Howard Street',
    apartment: 'Floor 3, Suite 310',
    city: 'San Francisco',
    state: 'CA',
    zipCode: '94105',
    country: 'United States',
    phone: '+1 (555) 389-2041',
    isDefaultShipping: false,
    isDefaultBilling: false,
    deliveryInstructions: 'Deliver during standard business hours (9am - 6pm).'
  },
  {
    id: 'addr_3',
    label: 'Weekend Retreat',
    recipientName: 'Alexander Hayes',
    street: '1280 Pine Meadow Road',
    city: 'Lake Tahoe',
    state: 'CA',
    zipCode: '96150',
    country: 'United States',
    phone: '+1 (555) 389-2041',
    isDefaultShipping: false,
    isDefaultBilling: false,
    deliveryInstructions: 'Place behind side gate near garage.'
  }
];

export const INITIAL_PAYMENT_CARDS: PaymentCard[] = [
  {
    id: 'card_1',
    cardType: 'visa',
    cardholderName: 'ALEXANDER HAYES',
    last4: '4288',
    expMonth: '08',
    expYear: '28',
    isDefault: true,
    bgColor: 'from-slate-900 via-indigo-950 to-slate-900'
  },
  {
    id: 'card_2',
    cardType: 'mastercard',
    cardholderName: 'ALEXANDER HAYES',
    last4: '8831',
    expMonth: '11',
    expYear: '27',
    isDefault: false,
    bgColor: 'from-zinc-900 via-stone-800 to-zinc-900'
  },
  {
    id: 'card_3',
    cardType: 'amex',
    cardholderName: 'ALEXANDER HAYES',
    last4: '1004',
    expMonth: '04',
    expYear: '29',
    isDefault: false,
    bgColor: 'from-emerald-950 via-teal-900 to-slate-900'
  }
];

export const INITIAL_ORDERS: Order[] = [
  {
    id: 'ord_9841',
    orderNumber: 'AUR-984102',
    date: 'August 12, 2026',
    status: 'out_for_delivery',
    carrier: 'FedEx Priority Express',
    trackingNumber: 'FX-88492019482',
    estimatedDelivery: 'Today by 4:30 PM',
    shippingAddress: INITIAL_ADDRESSES[0],
    paymentMethod: {
      cardType: 'visa',
      last4: '4288',
      name: 'Visa Signature'
    },
    subtotal: 489.00,
    shippingFee: 0.00,
    discount: 50.00,
    tax: 37.31,
    total: 476.31,
    notes: 'Signature required upon delivery.',
    items: [
      {
        id: 'item_1',
        productId: 'prod_audio_1',
        title: 'Aura Studio Wireless ANC Headphones',
        brand: 'Aura Acoustics',
        variant: 'Matte Obsidian',
        color: 'Obsidian Black',
        price: 349.00,
        originalPrice: 399.00,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=600&q=80',
        status: 'active',
        returnEligibleUntil: 'September 12, 2026'
      },
      {
        id: 'item_2',
        productId: 'prod_wear_1',
        title: 'Merino Wool Minimalist Zip Jacket',
        brand: 'Nordic Atelier',
        variant: 'Charcoal Grey / Medium',
        color: 'Charcoal',
        size: 'M',
        price: 140.00,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?auto=format&fit=crop&w=600&q=80',
        status: 'active',
        returnEligibleUntil: 'September 12, 2026'
      }
    ],
    trackingTimeline: [
      {
        status: 'processing',
        title: 'Order Confirmed & Prepared',
        description: 'Payment authorized. Items packaged at San Jose Fulfillment Center.',
        timestamp: 'Aug 10, 2026 - 10:14 AM',
        location: 'San Jose, CA',
        completed: true
      },
      {
        status: 'shipped',
        title: 'Departed Carrier Sorting Facility',
        description: 'Package scanned into FedEx Priority Express network.',
        timestamp: 'Aug 11, 2026 - 02:45 PM',
        location: 'Oakland Hub, CA',
        completed: true
      },
      {
        status: 'out_for_delivery',
        title: 'Out for Delivery with Courier',
        description: 'Driver Mark on route (Stop #14 on manifest). Delivery expected today.',
        timestamp: 'Aug 13, 2026 - 08:30 AM',
        location: 'San Francisco, CA',
        completed: true,
        current: true
      },
      {
        status: 'delivered',
        title: 'Delivered & Signed',
        description: 'Package handed directly to resident or concierge.',
        timestamp: 'Estimated Today by 4:30 PM',
        location: 'San Francisco, CA',
        completed: false
      }
    ]
  },
  {
    id: 'ord_9720',
    orderNumber: 'AUR-972049',
    date: 'August 04, 2026',
    status: 'shipped',
    carrier: 'DHL Express Global',
    trackingNumber: 'DHL-4920194821',
    estimatedDelivery: 'Tomorrow, Aug 14',
    shippingAddress: INITIAL_ADDRESSES[0],
    paymentMethod: {
      cardType: 'apple_pay',
      name: 'Apple Pay'
    },
    subtotal: 219.00,
    shippingFee: 0.00,
    discount: 20.00,
    tax: 16.92,
    total: 215.92,
    items: [
      {
        id: 'item_3',
        productId: 'prod_tech_2',
        title: 'Mechanical Linear Ergonomic Keyboard',
        brand: 'Keycraft Labs',
        variant: 'Hot-Swap Gateron Oil Kings / Wireless',
        color: 'Deep Space Grey',
        price: 219.00,
        originalPrice: 249.00,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=600&q=80',
        status: 'active',
        returnEligibleUntil: 'September 04, 2026'
      }
    ],
    trackingTimeline: [
      {
        status: 'processing',
        title: 'Order Verified',
        description: 'Order details verified & packed.',
        timestamp: 'Aug 04, 2026 - 11:20 AM',
        location: 'Los Angeles, CA',
        completed: true
      },
      {
        status: 'shipped',
        title: 'In Transit to Regional Hub',
        description: 'Package departed sorting center via DHL Express flight.',
        timestamp: 'Aug 12, 2026 - 06:15 PM',
        location: 'Sacramento, CA',
        completed: true,
        current: true
      },
      {
        status: 'out_for_delivery',
        title: 'Out for Delivery',
        description: 'Package arriving at local distribution station.',
        timestamp: 'Estimated Aug 14, 2026',
        location: 'San Francisco, CA',
        completed: false
      },
      {
        status: 'delivered',
        title: 'Delivery',
        description: 'Package delivered.',
        timestamp: 'Estimated Aug 14, 2026',
        location: 'San Francisco, CA',
        completed: false
      }
    ]
  },
  {
    id: 'ord_9511',
    orderNumber: 'AUR-951183',
    date: 'July 22, 2026',
    status: 'delivered',
    carrier: 'UPS Next Day Air',
    trackingNumber: '1Z9999999999999999',
    estimatedDelivery: 'Delivered July 24',
    deliveredDate: 'July 24, 2026 at 1:42 PM',
    shippingAddress: INITIAL_ADDRESSES[1],
    paymentMethod: {
      cardType: 'visa',
      last4: '4288',
      name: 'Visa Signature'
    },
    subtotal: 685.00,
    shippingFee: 0.00,
    discount: 68.50,
    tax: 52.40,
    total: 668.90,
    items: [
      {
        id: 'item_4',
        productId: 'prod_leather_1',
        title: 'Full-Grain Italian Leather Briefcase',
        brand: 'Vincenzo & Co',
        variant: 'Cognac Saddle Brown',
        color: 'Cognac Brown',
        price: 495.00,
        originalPrice: 550.00,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=600&q=80',
        status: 'delivered',
        userRating: 5,
        returnEligibleUntil: 'August 24, 2026'
      },
      {
        id: 'item_5',
        productId: 'prod_watch_1',
        title: 'Ceramic Smart Fitness Band V2',
        brand: 'Aura Track',
        variant: 'Titanium Grey / 44mm',
        price: 190.00,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=600&q=80',
        status: 'delivered',
        userRating: 5,
        returnEligibleUntil: 'August 24, 2026'
      }
    ],
    trackingTimeline: [
      {
        status: 'processing',
        title: 'Order Confirmed',
        description: 'Payment authorized.',
        timestamp: 'Jul 22, 2026',
        location: 'Austin, TX',
        completed: true
      },
      {
        status: 'shipped',
        title: 'Shipped via UPS',
        description: 'Picked up from logistics facility.',
        timestamp: 'Jul 23, 2026',
        location: 'Austin, TX',
        completed: true
      },
      {
        status: 'out_for_delivery',
        title: 'Out for Delivery',
        description: 'Delivered on truck.',
        timestamp: 'Jul 24, 2026',
        location: 'San Francisco, CA',
        completed: true
      },
      {
        status: 'delivered',
        title: 'Delivered to Receptionist',
        description: 'Signed by Building Concierge (M. Jenkins).',
        timestamp: 'Jul 24, 2026 - 01:42 PM',
        location: 'San Francisco, CA',
        completed: true,
        current: true
      }
    ]
  },
  {
    id: 'ord_9180',
    orderNumber: 'AUR-918022',
    date: 'June 18, 2026',
    status: 'delivered',
    carrier: 'FedEx Home Delivery',
    trackingNumber: 'FX-77382910492',
    estimatedDelivery: 'Delivered June 21',
    deliveredDate: 'June 21, 2026 at 11:15 AM',
    shippingAddress: INITIAL_ADDRESSES[0],
    paymentMethod: {
      cardType: 'mastercard',
      last4: '8831'
    },
    subtotal: 185.00,
    shippingFee: 12.00,
    discount: 0.00,
    tax: 15.73,
    total: 212.73,
    items: [
      {
        id: 'item_6',
        productId: 'prod_shoes_1',
        title: 'Minimalist Cloud Knit Low Sneakers',
        brand: 'Strata Footwear',
        variant: 'Chalk White / Size 10.5',
        color: 'Chalk White',
        size: 'US 10.5',
        price: 185.00,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=600&q=80',
        status: 'delivered',
        userRating: 4,
        returnEligibleUntil: 'July 21, 2026'
      }
    ],
    trackingTimeline: [
      {
        status: 'delivered',
        title: 'Delivered to Front Door',
        description: 'Left on porch as requested.',
        timestamp: 'Jun 21, 2026 - 11:15 AM',
        location: 'San Francisco, CA',
        completed: true,
        current: true
      }
    ]
  },
  {
    id: 'ord_8840',
    orderNumber: 'AUR-884019',
    date: 'May 05, 2026',
    status: 'returned',
    carrier: 'DHL Express',
    trackingNumber: 'DHL-1192849201',
    estimatedDelivery: 'Refund Issued May 14',
    deliveredDate: 'May 09, 2026',
    shippingAddress: INITIAL_ADDRESSES[0],
    paymentMethod: {
      cardType: 'visa',
      last4: '4288'
    },
    subtotal: 280.00,
    shippingFee: 0.00,
    discount: 30.00,
    tax: 21.25,
    total: 271.25,
    items: [
      {
        id: 'item_7',
        productId: 'prod_sunglasses_1',
        title: 'Titanium Aviator Polarized Eyewear',
        brand: 'Lumina Optics',
        variant: 'Gunmetal / Gradient Grey',
        price: 280.00,
        quantity: 1,
        image: 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?auto=format&fit=crop&w=600&q=80',
        status: 'returned',
        returnEligibleUntil: 'June 05, 2026'
      }
    ],
    trackingTimeline: [
      {
        status: 'returned',
        title: 'Refund Processed to Card •••• 4288',
        description: 'Item received at warehouse. Full refund of $271.25 issued.',
        timestamp: 'May 14, 2026 - 03:20 PM',
        location: 'Warehouse Center, NV',
        completed: true,
        current: true
      }
    ]
  }
];

export const INITIAL_WISHLIST: WishlistItem[] = [
  {
    id: 'wish_1',
    productId: 'prod_desk_1',
    title: 'Solid Walnut Precision Desk Shelf Organizer',
    brand: 'Grovemade Studio',
    price: 240.00,
    originalPrice: 280.00,
    inStock: true,
    stockCount: 8,
    image: 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?auto=format&fit=crop&w=600&q=80',
    rating: 4.9,
    reviewsCount: 124,
    category: 'Home & Studio',
    collection: 'Home Studio',
    dateAdded: 'Aug 08, 2026',
    priceDropped: true,
    previousPrice: 280.00
  },
  {
    id: 'wish_2',
    productId: 'prod_audio_2',
    title: 'Hi-Fi Studio Desktop Reference Monitors (Pair)',
    brand: 'Kanto Audio',
    price: 399.00,
    inStock: true,
    stockCount: 3,
    image: 'https://images.unsplash.com/photo-1545454675-3531b543be5d?auto=format&fit=crop&w=600&q=80',
    rating: 4.8,
    reviewsCount: 89,
    category: 'Audio & Tech',
    collection: 'Tech Upgrades',
    dateAdded: 'Aug 02, 2026'
  },
  {
    id: 'wish_3',
    productId: 'prod_apparel_3',
    title: 'Waterproof All-Weather Field Parka',
    brand: 'Aegis Outerwear',
    price: 320.00,
    originalPrice: 380.00,
    inStock: true,
    stockCount: 14,
    image: 'https://images.unsplash.com/photo-1544441893-675973e31985?auto=format&fit=crop&w=600&q=80',
    rating: 4.9,
    reviewsCount: 215,
    category: 'Apparel & Style',
    collection: 'Summer Wardrobe',
    dateAdded: 'Jul 28, 2026',
    priceDropped: true,
    previousPrice: 380.00
  },
  {
    id: 'wish_4',
    productId: 'prod_coffee_1',
    title: 'Precision Temperature Pour-Over Kettle',
    brand: 'Fellow Stagg',
    price: 165.00,
    inStock: false,
    stockCount: 0,
    image: 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=600&q=80',
    rating: 4.9,
    reviewsCount: 340,
    category: 'Home & Studio',
    collection: 'Gift Ideas',
    dateAdded: 'Jul 15, 2026'
  },
  {
    id: 'wish_5',
    productId: 'prod_leather_2',
    title: 'Handcrafted Vegetable-Tanned Card Wallet',
    brand: 'Vincenzo & Co',
    price: 75.00,
    originalPrice: 90.00,
    inStock: true,
    stockCount: 22,
    image: 'https://images.unsplash.com/photo-1627123424574-724758594e93?auto=format&fit=crop&w=600&q=80',
    rating: 4.7,
    reviewsCount: 68,
    category: 'Accessories',
    collection: 'Favorites',
    dateAdded: 'Jun 30, 2026'
  },
  {
    id: 'wish_6',
    productId: 'prod_lens_1',
    title: 'Anamorphic Cinema Smartphone Lens Kit',
    brand: 'Moment Optics',
    price: 149.00,
    inStock: true,
    stockCount: 5,
    image: 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=600&q=80',
    rating: 4.8,
    reviewsCount: 92,
    category: 'Audio & Tech',
    collection: 'Tech Upgrades',
    dateAdded: 'Jun 12, 2026'
  }
];

export const INITIAL_TRANSACTIONS: CreditTransaction[] = [
  {
    id: 'tx_1',
    type: 'points_redemption',
    amount: 25.00,
    description: 'VIP Reward Point Conversion ($25 Voucher)',
    date: 'Aug 10, 2026'
  },
  {
    id: 'tx_2',
    type: 'order_redemption',
    amount: -50.00,
    description: 'Applied on Order #AUR-984102',
    date: 'Aug 12, 2026'
  },
  {
    id: 'tx_3',
    type: 'referral_bonus',
    amount: 20.00,
    description: 'Friend Referral Bonus (Sarah M. signed up)',
    date: 'Jul 29, 2026'
  },
  {
    id: 'tx_4',
    type: 'credit_added',
    amount: 100.00,
    description: 'Gift Card E-Code Redeemed (GC-AURA-9821)',
    date: 'Jul 15, 2026',
    code: 'GC-AURA-9821'
  },
  {
    id: 'tx_5',
    type: 'refund',
    amount: 271.25,
    description: 'Return credit adjustment for Order #AUR-884019',
    date: 'May 14, 2026'
  }
];

export const INITIAL_VOUCHERS: RewardVoucher[] = [
  {
    id: 'vouch_1',
    title: '$15 Off Next Purchase',
    discountText: '$15 OFF',
    minSpend: 80,
    pointsCost: 400,
    code: 'VIP-SAVE15-H82',
    expiresAt: 'Sep 30, 2026',
    category: 'discount',
    unlocked: true
  },
  {
    id: 'vouch_2',
    title: 'Free FedEx Overnight Shipping',
    discountText: 'FREE OVERNIGHT',
    minSpend: 50,
    pointsCost: 650,
    code: 'EXPRESS-FREESHIP',
    expiresAt: 'Oct 15, 2026',
    category: 'shipping',
    unlocked: true
  },
  {
    id: 'vouch_3',
    title: '$35 Platinum Member Exclusive',
    discountText: '$35 OFF',
    minSpend: 150,
    pointsCost: 1000,
    code: 'PLATINUM-35-OFF',
    expiresAt: 'Nov 01, 2026',
    category: 'vip',
    unlocked: true
  },
  {
    id: 'vouch_4',
    title: '$60 Diamond Tier Voucher',
    discountText: '$60 OFF',
    minSpend: 250,
    pointsCost: 2000,
    code: 'DIAMOND-60-UNLOCKED',
    expiresAt: 'Dec 31, 2026',
    category: 'vip',
    unlocked: false
  },
  {
    id: 'vouch_5',
    title: 'Complimentary Leather Care Kit',
    discountText: 'FREE GIFT',
    minSpend: 0,
    pointsCost: 800,
    code: 'FREEGIFT-CAREKIT',
    expiresAt: 'Oct 31, 2026',
    category: 'gift',
    unlocked: true
  }
];

export const INITIAL_NOTIFICATIONS: NotificationItem[] = [
  {
    id: 'notif_1',
    title: 'Package Out for Delivery Today! 🚚',
    message: 'Your Order #AUR-984102 with Aura Studio Headphones is arriving by 4:30 PM.',
    time: '25 min ago',
    read: false,
    type: 'order',
    actionTab: 'orders',
    actionId: 'ord_9841'
  },
  {
    id: 'notif_2',
    title: 'Price Dropped on Your Wishlist Item 🏷️',
    message: 'Solid Walnut Desk Shelf dropped by $40. Only 8 units left in stock!',
    time: '3 hours ago',
    read: false,
    type: 'deal',
    actionTab: 'wishlist'
  },
  {
    id: 'notif_3',
    title: '+350 Loyalty Points Credited ✨',
    message: 'Your Platinum Tier monthly activity bonus has been added to your balance.',
    time: '1 day ago',
    read: true,
    type: 'reward',
    actionTab: 'rewards'
  },
  {
    id: 'notif_4',
    title: 'Security Alert: New Sign-in',
    message: 'Recognized Chrome browser session on macOS in San Francisco, CA.',
    time: '3 days ago',
    read: true,
    type: 'security',
    actionTab: 'profile'
  }
];

export const INITIAL_SUPPORT_TICKETS: SupportTicket[] = [
  {
    id: 'tkt_1029',
    ticketNumber: 'TKT-102984',
    subject: 'Address change confirmation before ship',
    orderId: 'AUR-984102',
    category: 'Shipping & Delivery',
    status: 'resolved',
    createdAt: 'Aug 10, 2026',
    lastUpdated: 'Aug 10, 2026 - 11:30 AM',
    messages: [
      {
        id: 'msg_1',
        sender: 'user',
        text: 'Hi, I wanted to double check if Apt 4B buzzer instructions are included on the shipping label.',
        time: 'Aug 10, 10:45 AM'
      },
      {
        id: 'msg_2',
        sender: 'agent',
        text: 'Hello Alexander! Yes, we have confirmed that "Apt 4B - Ring Buzzer #402" has been added to the FedEx carrier manifest for Order #AUR-984102.',
        time: 'Aug 10, 11:15 AM'
      },
      {
        id: 'msg_3',
        sender: 'system',
        text: 'Ticket resolved by Concierge Support.',
        time: 'Aug 10, 11:30 AM'
      }
    ]
  },
  {
    id: 'tkt_1012',
    ticketNumber: 'TKT-101248',
    subject: 'Refund status for returned sunglasses',
    orderId: 'AUR-884019',
    category: 'Returns & Refunds',
    status: 'resolved',
    createdAt: 'May 12, 2026',
    lastUpdated: 'May 14, 2026 - 03:25 PM',
    messages: [
      {
        id: 'msg_4',
        sender: 'user',
        text: 'Tracking indicates the warehouse received the return package. When will the card refund appear?',
        time: 'May 12, 02:10 PM'
      },
      {
        id: 'msg_5',
        sender: 'agent',
        text: 'Hi Alexander! The warehouse inspected the item and our billing team has just issued the full $271.25 refund to your Visa ending in 4288.',
        time: 'May 14, 03:20 PM'
      }
    ]
  }
];

export const RECOMMENDATIONS: ProductRecommendation[] = [
  {
    id: 'rec_1',
    title: 'Magnetic Wireless Fast Charging Dock',
    brand: 'Aura Power',
    price: 89.00,
    originalPrice: 110.00,
    image: 'https://images.unsplash.com/photo-1586816879360-004f5b0c51e5?auto=format&fit=crop&w=600&q=80',
    rating: 4.8,
    reviewsCount: 142,
    badge: 'Popular with Headphones',
    category: 'Tech Accessories'
  },
  {
    id: 'rec_2',
    title: 'Ultra-Soft Organic Pima Cotton Tee',
    brand: 'Nordic Atelier',
    price: 48.00,
    image: 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=600&q=80',
    rating: 4.9,
    reviewsCount: 480,
    badge: 'Frequently Reordered',
    category: 'Apparel'
  },
  {
    id: 'rec_3',
    title: 'Aroma Diffuser with Amber Glass',
    brand: 'Scent & Co',
    price: 65.00,
    originalPrice: 80.00,
    image: 'https://images.unsplash.com/photo-1608571423902-eed4a5ad8108?auto=format&fit=crop&w=600&q=80',
    rating: 4.7,
    reviewsCount: 95,
    badge: 'New Season',
    category: 'Home Wellness'
  },
  {
    id: 'rec_4',
    title: 'Anodized Aluminum Desk Organizer Tray',
    brand: 'Grovemade Studio',
    price: 55.00,
    image: 'https://images.unsplash.com/photo-1583847268964-b28dc8f51f92?auto=format&fit=crop&w=600&q=80',
    rating: 4.9,
    reviewsCount: 88,
    badge: 'Best Seller',
    category: 'Home & Studio'
  }
];

export const FAQ_ITEMS = [
  {
    category: 'Shipping',
    question: 'How fast will my order arrive?',
    answer: 'Standard shipping takes 3-5 business days. As a Platinum VIP member, all your orders receive complimentary FedEx Priority Express (1-2 business days with weekend delivery).'
  },
  {
    category: 'Returns',
    question: 'What is Aura’s return and exchange policy?',
    answer: 'We provide a 30-day hassle-free return guarantee. You can generate an instant prepaid QR drop-off code with zero printer requirement or request free doorstep courier pickup. Opting for Store Credit grants an additional 5% bonus credit.'
  },
  {
    category: 'Orders',
    question: 'Can I modify or cancel an order after placing it?',
    answer: 'Orders in the "Processing" stage can be cancelled or edited directly in the Orders tab within 1 hour. Once assigned a carrier tracking number, our 24/7 concierge can assist with redirecting delivery.'
  },
  {
    category: 'Rewards',
    question: 'How do VIP Loyalty Points and Tier upgrades work?',
    answer: 'You earn 1.5x points for every dollar spent on Aura. Points never expire and can be redeemed for store credit, instant checkout vouchers, and express shipping upgrades. Reach 5,000 points to unlock Diamond status.'
  },
  {
    category: 'Shipping',
    question: 'Do you ship internationally?',
    answer: 'Yes, we deliver worldwide via DHL Express Global to over 120 countries with all duties and taxes pre-calculated at checkout.'
  }
];

