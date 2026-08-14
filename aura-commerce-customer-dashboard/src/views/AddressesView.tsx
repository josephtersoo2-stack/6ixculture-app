import React, { useState } from 'react';
import { useDashboard } from '../context/DashboardContext';
import { 
  MapPin, 
  Plus, 
  Trash2, 
  Edit3, 
  Check, 
  Home, 
  Building2, 
  Phone, 
  Truck, 
  Sparkles,
  X
} from 'lucide-react';
import { Address } from '../types/dashboard';

export const AddressesView: React.FC = () => {
  const {
    addresses,
    addAddress,
    updateAddress,
    deleteAddress,
    setDefaultShipping,
    setDefaultBilling,
    showToast
  } = useDashboard();

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingAddress, setEditingAddress] = useState<Address | null>(null);

  // Form states
  const [label, setLabel] = useState('Home');
  const [recipientName, setRecipientName] = useState('');
  const [street, setStreet] = useState('');
  const [apartment, setApartment] = useState('');
  const [city, setCity] = useState('');
  const [state, setState] = useState('');
  const [zipCode, setZipCode] = useState('');
  const [country, setCountry] = useState('United States');
  const [phone, setPhone] = useState('');
  const [deliveryInstructions, setDeliveryInstructions] = useState('');
  const [isDefaultShipping, setIsDefaultShipping] = useState(false);
  const [isDefaultBilling, setIsDefaultBilling] = useState(false);

  const handleOpenAddModal = () => {
    setEditingAddress(null);
    setLabel('Home');
    setRecipientName('Alexander Hayes');
    setStreet('');
    setApartment('');
    setCity('');
    setState('');
    setZipCode('');
    setCountry('United States');
    setPhone('+1 (555) 389-2041');
    setDeliveryInstructions('');
    setIsDefaultShipping(false);
    setIsDefaultBilling(false);
    setIsModalOpen(true);
  };

  const handleOpenEditModal = (addr: Address) => {
    setEditingAddress(addr);
    setLabel(addr.label);
    setRecipientName(addr.recipientName);
    setStreet(addr.street);
    setApartment(addr.apartment || '');
    setCity(addr.city);
    setState(addr.state);
    setZipCode(addr.zipCode);
    setCountry(addr.country);
    setPhone(addr.phone);
    setDeliveryInstructions(addr.deliveryInstructions || '');
    setIsDefaultShipping(addr.isDefaultShipping);
    setIsDefaultBilling(addr.isDefaultBilling);
    setIsModalOpen(true);
  };

  const handleFillSample = () => {
    setLabel('Design Studio HQ');
    setRecipientName('Alexander Hayes (Studio)');
    setStreet('180 Montgomery St');
    setApartment('Suite 1400');
    setCity('San Francisco');
    setState('CA');
    setZipCode('94104');
    setCountry('United States');
    setPhone('+1 (555) 819-4920');
    setDeliveryInstructions('Call concierge at front lobby reception.');
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!recipientName || !street || !city || !state || !zipCode) {
      showToast('Missing Fields', 'Please complete all required address fields.', 'warning');
      return;
    }

    if (editingAddress) {
      updateAddress(editingAddress.id, {
        label,
        recipientName,
        street,
        apartment,
        city,
        state,
        zipCode,
        country,
        phone,
        deliveryInstructions,
        isDefaultShipping,
        isDefaultBilling
      });
    } else {
      addAddress({
        label,
        recipientName,
        street,
        apartment,
        city,
        state,
        zipCode,
        country,
        phone,
        deliveryInstructions,
        isDefaultShipping,
        isDefaultBilling
      });
    }
    setIsModalOpen(false);
  };

  return (
    <div className="space-y-6 max-w-7xl mx-auto pb-12">
      
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-display font-bold text-slate-900 tracking-tight">
            Address Book & Delivery Preferences
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-0.5">
            Manage primary delivery addresses, gate codes, and concierge instructions.
          </p>
        </div>

        <button
          onClick={handleOpenAddModal}
          className="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold flex items-center gap-2 shadow-xs transition-colors self-start sm:self-auto"
        >
          <Plus className="w-4 h-4" />
          <span>Add New Address</span>
        </button>
      </div>

      {/* Address Cards Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        {addresses.map((addr) => (
          <div
            key={addr.id}
            className={`bg-white rounded-3xl p-6 border transition-all flex flex-col justify-between relative shadow-xs hover:shadow-md ${
              addr.isDefaultShipping ? 'border-indigo-600 ring-2 ring-indigo-500/10' : 'border-slate-200/90'
            }`}
          >
            <div>
              {/* Top Header with Badges */}
              <div className="flex items-start justify-between gap-2 mb-3">
                <div className="flex items-center gap-2">
                  <div className="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center">
                    <MapPin className="w-4 h-4 text-indigo-600" />
                  </div>
                  <div>
                    <h3 className="text-sm font-bold text-slate-900">{addr.label}</h3>
                    <p className="text-[11px] text-slate-400 font-medium">{addr.country}</p>
                  </div>
                </div>

                <div className="flex flex-col gap-1 items-end">
                  {addr.isDefaultShipping && (
                    <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-indigo-100 text-indigo-800">
                      Default Shipping
                    </span>
                  )}
                  {addr.isDefaultBilling && (
                    <span className="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700">
                      Default Billing
                    </span>
                  )}
                </div>
              </div>

              {/* Recipient & Street Info */}
              <div className="space-y-1 text-xs text-slate-600 py-2">
                <p className="font-bold text-slate-900">{addr.recipientName}</p>
                <p>{addr.street} {addr.apartment ? `• ${addr.apartment}` : ''}</p>
                <p>{addr.city}, {addr.state} {addr.zipCode}</p>
                <p className="flex items-center gap-1 text-slate-500 pt-1">
                  <Phone className="w-3 h-3" />
                  {addr.phone}
                </p>
              </div>

              {/* Delivery Instructions */}
              {addr.deliveryInstructions && (
                <div className="mt-3 p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-[11px] text-slate-600">
                  <span className="font-semibold text-slate-800">Delivery note:</span> "{addr.deliveryInstructions}"
                </div>
              )}
            </div>

            {/* Bottom Actions */}
            <div className="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <button
                  onClick={() => handleOpenEditModal(addr)}
                  className="p-1.5 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors"
                  title="Edit Address"
                >
                  <Edit3 className="w-4 h-4" />
                </button>
                {addresses.length > 1 && (
                  <button
                    onClick={() => deleteAddress(addr.id)}
                    className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                    title="Delete Address"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                )}
              </div>

              {!addr.isDefaultShipping && (
                <button
                  onClick={() => setDefaultShipping(addr.id)}
                  className="text-xs font-bold text-indigo-600 hover:underline"
                >
                  Set as Default
                </button>
              )}
            </div>
          </div>
        ))}
      </div>

      {/* Add / Edit Address Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
              <div className="flex items-center gap-2">
                <MapPin className="w-4 h-4 text-indigo-600" />
                <h3 className="text-base font-bold text-slate-900">
                  {editingAddress ? 'Edit Address' : 'Add New Address'}
                </h3>
              </div>
              <div className="flex items-center gap-2">
                {!editingAddress && (
                  <button
                    type="button"
                    onClick={handleFillSample}
                    className="text-[11px] font-semibold text-indigo-600 bg-indigo-50 px-2 py-1 rounded-lg border border-indigo-100 hover:bg-indigo-100"
                  >
                    Auto-Fill Sample
                  </button>
                )}
                <button
                  onClick={() => setIsModalOpen(false)}
                  className="p-1 rounded-lg text-slate-400 hover:text-slate-700"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>
            </div>

            <form onSubmit={handleSubmit} className="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">
                  Address Label
                </label>
                <div className="grid grid-cols-3 gap-2">
                  {['Home', 'Office / Studio', 'Vacation Home'].map((l) => (
                    <button
                      key={l}
                      type="button"
                      onClick={() => setLabel(l)}
                      className={`py-1.5 px-2 rounded-xl text-xs font-semibold border transition-all ${
                        label === l ? 'border-indigo-600 bg-indigo-50 text-indigo-950' : 'border-slate-200 text-slate-600'
                      }`}
                    >
                      {l}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">
                  Recipient Full Name *
                </label>
                <input
                  type="text"
                  value={recipientName}
                  onChange={(e) => setRecipientName(e.target.value)}
                  className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                  required
                />
              </div>

              <div className="grid grid-cols-3 gap-3">
                <div className="col-span-2">
                  <label className="block text-xs font-bold text-slate-700 mb-1">
                    Street Address *
                  </label>
                  <input
                    type="text"
                    value={street}
                    onChange={(e) => setStreet(e.target.value)}
                    placeholder="e.g. 742 Evergreen Terrace"
                    className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">
                    Apt / Suite
                  </label>
                  <input
                    type="text"
                    value={apartment}
                    onChange={(e) => setApartment(e.target.value)}
                    placeholder="Apt 4B"
                    className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">
                    City *
                  </label>
                  <input
                    type="text"
                    value={city}
                    onChange={(e) => setCity(e.target.value)}
                    className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">
                    State / Region *
                  </label>
                  <input
                    type="text"
                    value={state}
                    onChange={(e) => setState(e.target.value)}
                    className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">
                    Postal / Zip *
                  </label>
                  <input
                    type="text"
                    value={zipCode}
                    onChange={(e) => setZipCode(e.target.value)}
                    className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                    required
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">
                  Phone Number *
                </label>
                <input
                  type="text"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                  required
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">
                  Delivery Notes & Instructions (Buzzer, Gate Code, Safe Spot)
                </label>
                <textarea
                  rows={2}
                  value={deliveryInstructions}
                  onChange={(e) => setDeliveryInstructions(e.target.value)}
                  placeholder="e.g. Ring buzzer #402. Leave with concierge if not home."
                  className="w-full text-xs p-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div className="space-y-2 pt-2">
                <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={isDefaultShipping}
                    onChange={(e) => setIsDefaultShipping(e.target.checked)}
                    className="w-4 h-4 text-indigo-600 rounded border-slate-300"
                  />
                  <span>Make this my default shipping address</span>
                </label>
                <label className="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={isDefaultBilling}
                    onChange={(e) => setIsDefaultBilling(e.target.checked)}
                    className="w-4 h-4 text-indigo-600 rounded border-slate-300"
                  />
                  <span>Make this my default billing address</span>
                </label>
              </div>

              <div className="flex gap-2 justify-end pt-3 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-slate-900 hover:bg-black text-white text-xs font-bold"
                >
                  Save Address
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
};
