import React, { useState } from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  X, 
  Star, 
  UploadCloud, 
  Sparkles, 
  Check, 
  Camera,
  ThumbsUp
} from 'lucide-react';
import { motion } from 'motion/react';
import { OrderItem } from '../types/dashboard';

interface ReviewModalProps {
  isOpen: boolean;
  orderId: string | null;
  item: OrderItem | null;
  onClose: () => void;
}

export const ReviewModal: React.FC<ReviewModalProps> = ({
  isOpen,
  orderId,
  item,
  onClose
}) => {
  const { addOrderReview } = useDashboard();

  const [rating, setRating] = useState<number>(5);
  const [hoverRating, setHoverRating] = useState<number>(0);
  const [fit, setFit] = useState<'Runs Small' | 'True to Size' | 'Runs Large'>('True to Size');
  const [reviewText, setReviewText] = useState('');
  const [headline, setHeadline] = useState('Exceptional craft and comfort!');
  const [hasPhoto, setHasPhoto] = useState(false);

  if (!isOpen || !item || !orderId) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    addOrderReview(orderId, item.id, rating, reviewText);
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-6">
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        exit={{ opacity: 0, scale: 0.95 }}
        className="bg-white rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200"
      >
        {/* Header */}
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
              <Star className="w-4 h-4 fill-amber-500" />
            </div>
            <div>
              <h3 className="text-base font-bold text-slate-900">Write a Verified Review</h3>
              <p className="text-xs text-slate-500">Earn +100 VIP Loyalty Points for sharing feedback</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit} className="p-6 space-y-5">
          {/* Target Product Summary */}
          <div className="p-3 bg-slate-50 rounded-2xl border border-slate-200 flex items-center gap-3">
            <img src={item.image} alt={item.title} className="w-12 h-12 rounded-xl object-cover" />
            <div className="min-w-0 flex-1">
              <p className="text-[11px] font-semibold text-indigo-600 uppercase tracking-wider">{item.brand}</p>
              <h4 className="text-xs font-bold text-slate-900 truncate">{item.title}</h4>
              <p className="text-[11px] text-slate-500">{item.variant || 'Standard edition'}</p>
            </div>
          </div>

          {/* Star Rating Selector */}
          <div className="text-center py-2">
            <label className="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">
              Overall Rating
            </label>
            <div className="flex items-center justify-center gap-2">
              {[1, 2, 3, 4, 5].map((star) => {
                const isFilled = (hoverRating || rating) >= star;
                return (
                  <button
                    key={star}
                    type="button"
                    onMouseEnter={() => setHoverRating(star)}
                    onMouseLeave={() => setHoverRating(0)}
                    onClick={() => setRating(star)}
                    className="p-1 transition-transform hover:scale-125 focus:outline-none"
                  >
                    <Star
                      className={`w-7 h-7 ${
                        isFilled
                          ? 'text-amber-400 fill-amber-400 drop-shadow-sm'
                          : 'text-slate-200'
                      }`}
                    />
                  </button>
                );
              })}
            </div>
            <p className="text-xs font-bold text-slate-700 mt-1">
              {rating === 5 ? '5.0 - Outstanding' :
               rating === 4 ? '4.0 - Very Good' :
               rating === 3 ? '3.0 - Average' :
               rating === 2 ? '2.0 - Below Average' : '1.0 - Poor'}
            </p>
          </div>

          {/* Fit & Sizing */}
          <div>
            <label className="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">
              Fit & Sizing Feedback
            </label>
            <div className="grid grid-cols-3 gap-2">
              {(['Runs Small', 'True to Size', 'Runs Large'] as const).map((opt) => (
                <button
                  key={opt}
                  type="button"
                  onClick={() => setFit(opt)}
                  className={`py-2 px-3 rounded-xl border text-xs font-semibold text-center transition-all ${
                    fit === opt
                      ? 'border-indigo-600 bg-indigo-50/60 text-indigo-950'
                      : 'border-slate-200 text-slate-600 hover:border-slate-300'
                  }`}
                >
                  {opt}
                </button>
              ))}
            </div>
          </div>

          {/* Review Headline & Body */}
          <div className="space-y-3">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Headline
              </label>
              <input
                type="text"
                value={headline}
                onChange={(e) => setHeadline(e.target.value)}
                placeholder="Sum up your experience in one sentence"
                className="w-full text-xs p-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                required
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Your Review
              </label>
              <textarea
                rows={3}
                value={reviewText}
                onChange={(e) => setReviewText(e.target.value)}
                placeholder="What did you like or dislike? How is the material quality and everyday performance?"
                className="w-full text-xs p-3 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                required
              />
            </div>
          </div>

          {/* Photo Attachment Mock */}
          <div>
            <div
              onClick={() => setHasPhoto(!hasPhoto)}
              className={`border-2 border-dashed rounded-2xl p-3 text-center cursor-pointer transition-all flex items-center justify-center gap-2 ${
                hasPhoto
                  ? 'border-emerald-500 bg-emerald-50/50 text-emerald-800'
                  : 'border-slate-200 hover:border-slate-300 text-slate-500'
              }`}
            >
              {hasPhoto ? (
                <>
                  <Check className="w-4 h-4 text-emerald-600" />
                  <span className="text-xs font-semibold">1 Product Photo Attached</span>
                </>
              ) : (
                <>
                  <Camera className="w-4 h-4 text-slate-400" />
                  <span className="text-xs">Add customer photos (Earn bonus 50 pts)</span>
                </>
              )}
            </div>
          </div>

          {/* Submit */}
          <div className="pt-2">
            <button
              type="submit"
              className="w-full py-3 px-4 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold flex items-center justify-center gap-2 shadow-md transition-all"
            >
              <Sparkles className="w-4 h-4 text-amber-400" />
              <span>Submit Review & Claim +100 Points</span>
            </button>
          </div>
        </form>
      </motion.div>
    </div>
  );
};
