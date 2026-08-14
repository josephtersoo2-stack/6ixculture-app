import React, { useState } from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  Heart, 
  ShoppingBag, 
  Trash2, 
  Share2, 
  Plus, 
  FolderPlus, 
  Tag, 
  Star, 
  Check, 
  Sparkles, 
  AlertCircle,
  ArrowUpDown,
  X
} from 'lucide-react';
import { WishlistItem } from '../types/dashboard';

export const WishlistView: React.FC = () => {
  const {
    wishlist,
    wishlistCollections,
    activeWishlistCollection,
    setActiveWishlistCollection,
    removeFromWishlist,
    moveWishlistToCart,
    moveAllWishlistToCart,
    createCollection,
    formatPrice,
    showToast
  } = useDashboard();

  const [searchQuery, setSearchQuery] = useState('');
  const [sortBy, setSortBy] = useState<'newest' | 'price_low' | 'price_high' | 'discount'>('newest');
  const [isNewCollectionModalOpen, setIsNewCollectionModalOpen] = useState(false);
  const [newCollectionName, setNewCollectionName] = useState('');

  // Filter items by collection and search
  const filteredWishlist = wishlist.filter((item) => {
    if (activeWishlistCollection !== 'All' && item.collection !== activeWishlistCollection) {
      return false;
    }
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      return (
        item.title.toLowerCase().includes(q) ||
        item.brand.toLowerCase().includes(q) ||
        item.category.toLowerCase().includes(q)
      );
    }
    return true;
  });

  // Sort items
  const sortedWishlist = [...filteredWishlist].sort((a, b) => {
    if (sortBy === 'price_low') return a.price - b.price;
    if (sortBy === 'price_high') return b.price - a.price;
    if (sortBy === 'discount') {
      const discA = a.originalPrice ? a.originalPrice - a.price : 0;
      const discB = b.originalPrice ? b.originalPrice - b.price : 0;
      return discB - discA;
    }
    return 0;
  });

  const inStockCount = wishlist.filter((w) => w.inStock).length;
  const totalWishlistValue = wishlist.reduce((acc, it) => acc + it.price, 0);

  const handleShareWishlist = () => {
    const url = window.location.href;
    navigator.clipboard.writeText(url);
    showToast('Wishlist Link Copied', 'Shareable link copied to clipboard! Anyone with the link can view your list.', 'success');
  };

  const handleCreateCollection = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newCollectionName.trim()) return;
    createCollection(newCollectionName.trim());
    setNewCollectionName('');
    setIsNewCollectionModalOpen(false);
  };

  return (
    <div className="space-y-6 max-w-7xl mx-auto pb-12">
      
      {/* Top Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-display font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <span>Saved & Wishlist</span>
            <span className="text-sm font-semibold text-rose-600 bg-rose-50 px-2.5 py-0.5 rounded-full border border-rose-100">
              {wishlist.length} Items
            </span>
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-0.5">
            Organize dream items into custom collections, monitor price drops, and move to bag.
          </p>
        </div>

        <div className="flex items-center gap-2.5 flex-wrap">
          <button
            onClick={handleShareWishlist}
            className="px-3.5 py-2 rounded-xl border border-slate-200 hover:bg-white text-xs font-semibold text-slate-700 flex items-center gap-1.5 transition-colors shadow-xs"
          >
            <Share2 className="w-3.5 h-3.5 text-slate-500" />
            <span>Share List</span>
          </button>

          <button
            onClick={moveAllWishlistToCart}
            disabled={inStockCount === 0}
            className="px-4 py-2 rounded-xl bg-slate-900 hover:bg-black disabled:opacity-50 text-white text-xs font-bold flex items-center gap-2 shadow-xs transition-colors"
          >
            <ShoppingBag className="w-3.5 h-3.5" />
            <span>Move All In-Stock ({inStockCount}) to Bag</span>
          </button>
        </div>
      </div>

      {/* Collection Navigation Tabs */}
      <div className="bg-white rounded-3xl p-4 sm:p-5 border border-slate-200/90 shadow-sm space-y-4">
        <div className="flex items-center justify-between gap-3 overflow-x-auto pb-1">
          <div className="flex items-center gap-1.5 sm:gap-2">
            {wishlistCollections.map((col) => {
              const isSelected = activeWishlistCollection === col;
              const count = col === 'All' 
                ? wishlist.length 
                : wishlist.filter(w => w.collection === col).length;

              return (
                <button
                  key={col}
                  onClick={() => setActiveWishlistCollection(col)}
                  className={`px-3.5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap flex items-center gap-1.5 transition-all ${
                    isSelected
                      ? 'bg-slate-900 text-white shadow-xs'
                      : 'bg-slate-100/80 text-slate-600 hover:bg-slate-200/70'
                  }`}
                >
                  <span>{col}</span>
                  <span className={`text-[10px] font-bold px-1.5 py-0.2 rounded-md ${
                    isSelected ? 'bg-slate-800 text-slate-200' : 'bg-white text-slate-700'
                  }`}>
                    {count}
                  </span>
                </button>
              );
            })}

            <button
              onClick={() => setIsNewCollectionModalOpen(true)}
              className="px-3 py-2 rounded-xl border border-dashed border-slate-300 text-slate-600 hover:border-indigo-500 hover:text-indigo-600 text-xs font-semibold flex items-center gap-1 whitespace-nowrap transition-colors"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>New Collection</span>
            </button>
          </div>
        </div>

        {/* Filter & Sort Bar */}
        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-100">
          <input
            type="text"
            placeholder="Search saved items..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full sm:w-72 px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-indigo-500"
          />

          <div className="flex items-center gap-2 w-full sm:w-auto justify-between sm:justify-end">
            <span className="text-xs text-slate-500">
              Total Value: <span className="font-bold text-slate-900">{formatPrice(totalWishlistValue)}</span>
            </span>

            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value as any)}
              className="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none cursor-pointer"
            >
              <option value="newest">Recently Added</option>
              <option value="price_low">Price: Low to High</option>
              <option value="price_high">Price: High to Low</option>
              <option value="discount">Biggest Savings</option>
            </select>
          </div>
        </div>
      </div>

      {/* Wishlist Items Grid */}
      {sortedWishlist.length === 0 ? (
        <div className="bg-white rounded-3xl p-12 text-center border border-slate-200/90 shadow-sm">
          <div className="w-16 h-16 rounded-full bg-rose-50 text-rose-400 flex items-center justify-center mx-auto mb-3">
            <Heart className="w-8 h-8" />
          </div>
          <h3 className="text-base font-bold text-slate-900">No items in this collection</h3>
          <p className="text-xs text-slate-500 mt-1 max-w-xs mx-auto">
            Browse our curated recommendations or search for products to save for later.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          {sortedWishlist.map((item) => (
            <div
              key={item.id}
              className="bg-white rounded-3xl border border-slate-200/90 overflow-hidden shadow-xs hover:shadow-md hover:border-slate-300 transition-all flex flex-col justify-between group"
            >
              <div>
                {/* Image Container */}
                <div className="relative aspect-4/3 bg-slate-100 overflow-hidden">
                  <img
                    src={item.image}
                    alt={item.title}
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  />
                  
                  {/* Badges */}
                  <div className="absolute top-3 left-3 flex flex-col gap-1.5">
                    {item.priceDropped && (
                      <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-rose-600 text-white shadow-xs flex items-center gap-1">
                        <Tag className="w-3 h-3" />
                        Price Dropped!
                      </span>
                    )}
                    {!item.inStock ? (
                      <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-900/80 text-white backdrop-blur-xs">
                        Out of Stock
                      </span>
                    ) : item.stockCount <= 3 ? (
                      <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-amber-500 text-white shadow-xs">
                        Only {item.stockCount} Left
                      </span>
                    ) : (
                      <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-600 text-white shadow-xs">
                        In Stock
                      </span>
                    )}
                  </div>

                  {/* Remove Button */}
                  <button
                    onClick={() => removeFromWishlist(item.id)}
                    className="absolute top-3 right-3 p-2 rounded-full bg-white/80 backdrop-blur-md text-slate-500 hover:text-rose-600 hover:bg-white transition-all shadow-xs"
                    title="Remove from wishlist"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>

                {/* Content */}
                <div className="p-5 space-y-2">
                  <div className="flex items-center justify-between">
                    <p className="text-[11px] font-semibold text-indigo-600 uppercase tracking-wider">
                      {item.brand}
                    </p>
                    <span className="text-[10px] font-medium text-slate-400 bg-slate-100 px-2 py-0.5 rounded">
                      {item.collection}
                    </span>
                  </div>

                  <h3 className="text-sm font-bold text-slate-900 line-clamp-1">{item.title}</h3>

                  <div className="flex items-center gap-1 text-xs text-slate-500">
                    <Star className="w-3.5 h-3.5 text-amber-400 fill-amber-400" />
                    <span className="font-bold text-slate-700">{item.rating}</span>
                    <span>({item.reviewsCount} reviews)</span>
                  </div>

                  <div className="flex items-baseline gap-2 pt-1">
                    <span className="text-base font-bold text-slate-900">
                      {formatPrice(item.price)}
                    </span>
                    {item.originalPrice && (
                      <span className="text-xs text-slate-400 line-through">
                        {formatPrice(item.originalPrice)}
                      </span>
                    )}
                  </div>
                </div>
              </div>

              {/* Bottom Action */}
              <div className="p-5 pt-0">
                <button
                  onClick={() => moveWishlistToCart(item)}
                  disabled={!item.inStock}
                  className={`w-full py-2.5 px-4 rounded-xl text-xs font-bold flex items-center justify-center gap-2 transition-all ${
                    item.inStock
                      ? 'bg-slate-900 hover:bg-black text-white shadow-xs'
                      : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                  }`}
                >
                  <ShoppingBag className="w-3.5 h-3.5" />
                  <span>{item.inStock ? 'Move to Shopping Bag' : 'Notify When Back in Stock'}</span>
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Create New Collection Modal */}
      {isNewCollectionModalOpen && (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl p-6 max-w-md w-full shadow-2xl border border-slate-200">
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2">
                <FolderPlus className="w-5 h-5 text-indigo-600" />
                <h3 className="text-base font-bold text-slate-900">Create New Collection</h3>
              </div>
              <button
                onClick={() => setIsNewCollectionModalOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-700"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleCreateCollection} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">
                  Collection Name
                </label>
                <input
                  type="text"
                  placeholder="e.g. Winter Essentials, Living Room Upgrade"
                  value={newCollectionName}
                  onChange={(e) => setNewCollectionName(e.target.value)}
                  className="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500"
                  autoFocus
                  required
                />
              </div>

              <div className="flex gap-2 justify-end pt-2">
                <button
                  type="button"
                  onClick={() => setIsNewCollectionModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold"
                >
                  Create Collection
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
};
