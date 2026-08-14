import React from 'react';
import { useDashboard } from '../context/DashboardContext';
import { CheckCircle2, AlertCircle, Info, AlertTriangle, X } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';

export const ToastContainer: React.FC = () => {
  const { toasts, dismissToast } = useDashboard();

  return (
    <div className="fixed bottom-20 md:bottom-6 right-4 md:right-6 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none">
      <AnimatePresence>
        {toasts.map((toast) => {
          let Icon = CheckCircle2;
          let borderColor = 'border-emerald-200 bg-emerald-50/95 text-emerald-900';
          let iconColor = 'text-emerald-600';

          if (toast.type === 'error') {
            Icon = AlertCircle;
            borderColor = 'border-rose-200 bg-rose-50/95 text-rose-900';
            iconColor = 'text-rose-600';
          } else if (toast.type === 'warning') {
            Icon = AlertTriangle;
            borderColor = 'border-amber-200 bg-amber-50/95 text-amber-900';
            iconColor = 'text-amber-600';
          } else if (toast.type === 'info') {
            Icon = Info;
            borderColor = 'border-sky-200 bg-sky-50/95 text-sky-900';
            iconColor = 'text-sky-600';
          }

          return (
            <motion.div
              key={toast.id}
              initial={{ opacity: 0, y: 20, scale: 0.95 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={{ opacity: 0, scale: 0.9, transition: { duration: 0.15 } }}
              layout
              className={`pointer-events-auto p-4 rounded-xl border shadow-lg backdrop-blur-md flex items-start gap-3 ${borderColor}`}
            >
              <Icon className={`w-5 h-5 shrink-0 mt-0.5 ${iconColor}`} />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold tracking-tight">{toast.title}</p>
                {toast.description && (
                  <p className="text-xs opacity-90 mt-0.5 leading-relaxed">{toast.description}</p>
                )}
              </div>
              <button
                onClick={() => dismissToast(toast.id)}
                className="p-1 rounded-lg opacity-60 hover:opacity-100 hover:bg-black/5 transition-colors"
              >
                <X className="w-3.5 h-3.5" />
              </button>
            </motion.div>
          );
        })}
      </AnimatePresence>
    </div>
  );
};
