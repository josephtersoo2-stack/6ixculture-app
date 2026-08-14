import React, { useState } from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  User, 
  ShieldCheck, 
  Bell, 
  Globe, 
  Smartphone, 
  Key, 
  Check, 
  Sparkles, 
  Laptop, 
  LogOut,
  Save,
  AlertTriangle,
  Camera
} from 'lucide-react';

export const ProfileView: React.FC = () => {
  const {
    user,
    updateProfile,
    currency,
    setCurrency,
    showToast
  } = useDashboard();

  // Profile Form States
  const [firstName, setFirstName] = useState(user.firstName);
  const [lastName, setLastName] = useState(user.lastName);
  const [email, setEmail] = useState(user.email);
  const [phone, setPhone] = useState(user.phone);
  const [avatar, setAvatar] = useState(user.avatar);
  const [twoFactorEnabled, setTwoFactorEnabled] = useState(user.twoFactorEnabled);

  // Notifications
  const [notifyOrders, setNotifyOrders] = useState(user.notificationPreferences.orderUpdates);
  const [notifyPromos, setNotifyPromos] = useState(user.notificationPreferences.promotions);
  const [notifyPriceDrops, setNotifyPriceDrops] = useState(user.notificationPreferences.priceDrops);
  const [notifyNewsletter, setNotifyNewsletter] = useState(user.notificationPreferences.newsletter);

  // Password Modal/State
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');

  const [activeSessions, setActiveSessions] = useState([
    { id: 'sess_1', device: 'MacBook Pro 16" (Sonoma)', location: 'San Francisco, CA, USA', lastActive: 'Active Now', isCurrent: true },
    { id: 'sess_2', device: 'iPhone 16 Pro (iOS 18)', location: 'San Francisco, CA, USA', lastActive: '2 hours ago', isCurrent: false },
    { id: 'sess_3', device: 'iPad Air 5th Gen (iPadOS)', location: 'Palo Alto, CA, USA', lastActive: '3 days ago', isCurrent: false }
  ]);

  const handleSaveProfile = (e: React.FormEvent) => {
    e.preventDefault();
    updateProfile({
      firstName,
      lastName,
      email,
      phone,
      avatar,
      twoFactorEnabled,
      notificationPreferences: {
        orderUpdates: notifyOrders,
        promotions: notifyPromos,
        priceDrops: notifyPriceDrops,
        newsletter: notifyNewsletter
      }
    });
  };

  const handlePasswordUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!currentPassword || !newPassword) {
      showToast('Missing Fields', 'Please enter your current and new password.', 'warning');
      return;
    }
    if (newPassword !== confirmPassword) {
      showToast('Password Mismatch', 'New passwords do not match.', 'error');
      return;
    }
    showToast('Password Updated', 'Your security credentials have been updated securely.', 'success');
    setCurrentPassword('');
    setNewPassword('');
    setConfirmPassword('');
  };

  const handleRevokeOtherSessions = () => {
    setActiveSessions(activeSessions.filter(s => s.isCurrent));
    showToast('Sessions Terminated', 'Signed out of all other devices successfully.', 'success');
  };

  return (
    <div className="space-y-6 max-w-7xl mx-auto pb-12">
      
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-display font-bold text-slate-900 tracking-tight">
            Account Settings & Security
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-0.5">
            Manage your personal profile, 2FA credentials, notifications, and active devices.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {/* Left Column: Personal Information Form */}
        <div className="lg:col-span-2 space-y-6">
          
          {/* Profile Card */}
          <form onSubmit={handleSaveProfile} className="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-sm space-y-6">
            <div className="flex items-center justify-between pb-4 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <User className="w-5 h-5 text-indigo-600" />
                <h3 className="text-base font-bold text-slate-900">Personal Information</h3>
              </div>
              <span className="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">
                {user.tier} VIP
              </span>
            </div>

            {/* Avatar Row */}
            <div className="flex items-center gap-4">
              <div className="relative">
                <img
                  src={avatar}
                  alt={user.firstName}
                  className="w-18 h-18 rounded-2xl object-cover ring-4 ring-slate-100 shadow-sm"
                />
                <button
                  type="button"
                  onClick={() => {
                    const newAvatar = avatar.includes('men') 
                      ? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80'
                      : 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&auto=format&fit=crop&q=80';
                    setAvatar(newAvatar);
                  }}
                  className="absolute -bottom-1 -right-1 p-1.5 rounded-full bg-slate-900 text-white hover:bg-black shadow-xs transition-colors"
                  title="Change Avatar"
                >
                  <Camera className="w-3.5 h-3.5" />
                </button>
              </div>
              <div>
                <h4 className="text-sm font-bold text-slate-900">{firstName} {lastName}</h4>
                <p className="text-xs text-slate-500">Member since October 2024</p>
                <p className="text-[11px] text-indigo-600 font-semibold mt-0.5">Click camera icon to cycle profile picture</p>
              </div>
            </div>

            {/* Inputs Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">First Name</label>
                <input
                  type="text"
                  value={firstName}
                  onChange={(e) => setFirstName(e.target.value)}
                  className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                  required
                />
              </div>
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Last Name</label>
                <input
                  type="text"
                  value={lastName}
                  onChange={(e) => setLastName(e.target.value)}
                  className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                  required
                />
              </div>
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                  required
                />
              </div>
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Phone Number</label>
                <input
                  type="text"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                  required
                />
              </div>
            </div>

            {/* Save Button */}
            <div className="pt-2 flex justify-end">
              <button
                type="submit"
                className="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold flex items-center gap-2 shadow-xs transition-colors"
              >
                <Save className="w-4 h-4" />
                <span>Save Profile Changes</span>
              </button>
            </div>
          </form>

          {/* Notification Preferences */}
          <div className="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-sm space-y-4">
            <div className="flex items-center gap-2 pb-3 border-b border-slate-100">
              <Bell className="w-5 h-5 text-indigo-600" />
              <h3 className="text-base font-bold text-slate-900">Notification Preferences</h3>
            </div>

            <div className="space-y-3">
              {[
                { title: 'Order & Shipping Tracking Alerts', desc: 'Real-time SMS & email notifications when packages depart sorting or arrive.', state: notifyOrders, set: setNotifyOrders },
                { title: 'Wishlist Price Drop Alerts', desc: 'Instant alerts whenever an item in your saved wishlist goes on sale or low stock.', state: notifyPriceDrops, set: setNotifyPriceDrops },
                { title: 'VIP Perks & Birthday Vouchers', desc: 'Exclusive invitations to secret drops, double points events, and VIP promotions.', state: notifyPromos, set: setNotifyPromos },
                { title: 'Aura Weekly Curated Drops', desc: 'A weekly design digest highlighting new designer collaborations.', state: notifyNewsletter, set: setNotifyNewsletter }
              ].map((item, idx) => (
                <div key={idx} className="flex items-center justify-between p-3 rounded-2xl bg-slate-50 border border-slate-100">
                  <div className="max-w-md">
                    <p className="text-xs font-bold text-slate-900">{item.title}</p>
                    <p className="text-[11px] text-slate-500">{item.desc}</p>
                  </div>
                  <button
                    type="button"
                    onClick={() => item.set(!item.state)}
                    className={`w-11 h-6 rounded-full transition-colors relative ${
                      item.state ? 'bg-indigo-600' : 'bg-slate-300'
                    }`}
                  >
                    <div className={`w-4 h-4 rounded-full bg-white transition-transform ${
                      item.state ? 'translate-x-6' : 'translate-x-1'
                    }`} />
                  </button>
                </div>
              ))}
            </div>
          </div>

        </div>

        {/* Right Column: Security, 2FA, Active Sessions, Currency */}
        <div className="space-y-6">
          
          {/* Security & 2FA */}
          <div className="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-sm space-y-5">
            <div className="flex items-center gap-2 pb-3 border-b border-slate-100">
              <ShieldCheck className="w-5 h-5 text-emerald-600" />
              <h3 className="text-base font-bold text-slate-900">Security & Authentication</h3>
            </div>

            {/* 2FA Toggle */}
            <div className="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between">
              <div>
                <p className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                  <Smartphone className="w-3.5 h-3.5 text-indigo-600" />
                  Two-Factor Auth (2FA)
                </p>
                <p className="text-[11px] text-slate-500 mt-0.5">
                  {twoFactorEnabled ? 'Protected via Authenticator App' : 'Disabled — We recommend enabling'}
                </p>
              </div>

              <button
                type="button"
                onClick={() => {
                  setTwoFactorEnabled(!twoFactorEnabled);
                  showToast(
                    twoFactorEnabled ? '2FA Disabled' : '2FA Enabled',
                    twoFactorEnabled ? 'Two-factor verification disabled.' : 'Authenticator verification activated.',
                    twoFactorEnabled ? 'warning' : 'success'
                  );
                }}
                className={`w-11 h-6 rounded-full transition-colors relative ${
                  twoFactorEnabled ? 'bg-emerald-600' : 'bg-slate-300'
                }`}
              >
                <div className={`w-4 h-4 rounded-full bg-white transition-transform ${
                  twoFactorEnabled ? 'translate-x-6' : 'translate-x-1'
                }`} />
              </button>
            </div>

            {/* Password Change Form */}
            <form onSubmit={handlePasswordUpdate} className="space-y-3 pt-1">
              <p className="text-xs font-bold text-slate-800 uppercase tracking-wider">Change Password</p>
              
              <input
                type="password"
                placeholder="Current Password"
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
              />
              <input
                type="password"
                placeholder="New Password (min 8 chars)"
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
              />
              <input
                type="password"
                placeholder="Confirm New Password"
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
              />

              <button
                type="submit"
                className="w-full py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold transition-colors"
              >
                Update Password
              </button>
            </form>
          </div>

          {/* Active Sessions */}
          <div className="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-sm space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div className="flex items-center gap-2">
                <Laptop className="w-5 h-5 text-indigo-600" />
                <h3 className="text-sm font-bold text-slate-900">Active Devices</h3>
              </div>
              {activeSessions.length > 1 && (
                <button
                  onClick={handleRevokeOtherSessions}
                  className="text-[11px] font-bold text-rose-600 hover:underline flex items-center gap-1"
                >
                  <LogOut className="w-3 h-3" />
                  Sign Out Others
                </button>
              )}
            </div>

            <div className="space-y-3">
              {activeSessions.map((s) => (
                <div key={s.id} className="p-3 rounded-2xl bg-slate-50 border border-slate-100 text-xs space-y-0.5">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-slate-900">{s.device}</span>
                    {s.isCurrent ? (
                      <span className="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-1.5 py-0.2 rounded">
                        Current Session
                      </span>
                    ) : (
                      <span className="text-[10px] text-slate-400">{s.lastActive}</span>
                    )}
                  </div>
                  <p className="text-[11px] text-slate-500">{s.location}</p>
                </div>
              ))}
            </div>
          </div>

          {/* Regional Currency Preference */}
          <div className="bg-white rounded-3xl p-6 border border-slate-200/90 shadow-sm space-y-3">
            <div className="flex items-center gap-2 pb-2 border-b border-slate-100">
              <Globe className="w-5 h-5 text-indigo-600" />
              <h3 className="text-sm font-bold text-slate-900">Currency & Region</h3>
            </div>
            
            <div className="space-y-1">
              <label className="block text-xs font-semibold text-slate-700">Display Currency</label>
              <select
                value={currency}
                onChange={(e) => setCurrency(e.target.value as any)}
                className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-slate-800 focus:outline-none cursor-pointer"
              >
                <option value="USD">USD ($) — United States Dollar</option>
                <option value="EUR">EUR (€) — Euro</option>
                <option value="GBP">GBP (£) — British Pound</option>
                <option value="JPY">JPY (¥) — Japanese Yen</option>
                <option value="CAD">CAD ($) — Canadian Dollar</option>
              </select>
            </div>
          </div>

        </div>

      </div>

    </div>
  );
};
