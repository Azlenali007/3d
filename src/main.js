import Alpine from 'alpinejs';
import { gsap } from 'gsap';
import './index.css';

// Register Alpine and expose globally for inline directives
window.Alpine = Alpine;
window.gsap = gsap;

// Global SMM App State & Controller
Alpine.data('smmApp', () => ({
  // Navigation & Page State
  currentPage: 'landing', // landing, dashboard, new-order, services, orders, wallet, transactions, tickets, profile, login, admin
  authTab: 'login', // login, register
  previousPage: 'landing',

  // User & Auth
  user: null,
  isAuthenticated: false,
  loginForm: { email: 'aarisali@gmail.com', password: 'password123', remember: true },
  registerForm: { name: '', email: '', phone: '', password: '' },
  profileForm: { name: '', phone: '', email: '' },
  passwordForm: { current_password: '', new_password: '' },
  authError: '',
  authSuccess: '',

  // System Settings
  settings: {
    site_name: 'SMM Panel',
    site_tagline: 'Grow Your Social Media',
    currency_symbol: '₹',
  },

  // 3D Carousel State (Hero & Dashboard)
  carouselIndex: 1, // 0: Add Funds, 1: New Order, 2: My Orders, 3: Services, 4: Support
  carouselCards: [
    {
      id: 'wallet',
      title: 'Add Funds',
      subtitle: 'Top up your wallet',
      color: 'from-emerald-500 to-teal-600',
      glowClass: 'card-3d-glow-green',
      icon: 'wallet',
      badge: 'Instant QR & UPI',
      actionPage: 'wallet'
    },
    {
      id: 'new-order',
      title: 'New Order',
      subtitle: 'Place a new order for your social media',
      color: 'from-blue-600 via-indigo-600 to-cyan-500',
      glowClass: 'card-3d-glow-blue',
      icon: 'lightning',
      badge: 'Fast Delivery',
      actionPage: 'new-order'
    },
    {
      id: 'orders',
      title: 'My Orders',
      subtitle: 'Track your orders',
      color: 'from-amber-500 to-orange-600',
      glowClass: 'card-3d-glow-amber',
      icon: 'box',
      badge: 'Live Status',
      actionPage: 'orders'
    },
    {
      id: 'services',
      title: 'Services',
      subtitle: 'Browse all 20+ social media services',
      color: 'from-purple-600 to-pink-600',
      glowClass: 'card-3d-glow-purple',
      icon: 'grid',
      badge: 'Best Rates',
      actionPage: 'services'
    },
    {
      id: 'tickets',
      title: 'Support',
      subtitle: '24/7 Priority support desk',
      color: 'from-sky-500 to-blue-700',
      glowClass: 'card-3d-glow-blue',
      icon: 'help',
      badge: 'Ticket Center',
      actionPage: 'tickets'
    }
  ],
  isDraggingCarousel: false,
  carouselStartX: 0,
  carouselCurrentX: 0,

  // Services & Categories
  categories: [],
  services: [],
  selectedCategorySlug: 'instagram',
  serviceSearchQuery: '',
  selectedService: null,

  // New Order State
  orderStep: 1, // 1: Select Service, 2: Details & Link, 3: Payment Confirmation
  orderForm: {
    service_id: null,
    link: '',
    quantity: 1000,
    calculatedCharge: 0
  },
  orderError: '',
  orderSuccess: null,
  isPlacingOrder: false,

  // Orders State
  orders: [],
  orderStatusFilter: 'All', // All, Processing, Completed, Cancelled
  selectedOrderModal: null,

  // Wallet & Deposit State
  depositAmount: 200,
  selectedPaymentMethod: 'Razorpay',
  depositSuccess: '',
  depositError: '',
  isDepositing: false,
  transactions: [],
  transactionFilter: 'All', // All, Add Funds, Orders, Refunds

  // Support Tickets State
  tickets: [],
  ticketFilter: 'All', // All, Open, In Progress, Closed
  showNewTicketModal: false,
  newTicketForm: { subject: '', order_id: '', priority: 'medium', message: '' },
  activeTicket: null,
  ticketReplyMessage: '',
  ticketError: '',

  // Admin State
  adminStats: null,
  adminUsers: [],
  adminOrders: [],
  adminTransactions: [],
  adminActiveTab: 'dashboard', // dashboard, users, orders, services, payments, settings, profile
  adminBalanceModalUser: null,
  adminBalanceAmount: 100,
  adminBalanceAction: 'add',
  adminProfileForm: { name: 'Admin Director', password: '' },
  adminSettingsForm: { site_name: 'SMM Panel', site_tagline: 'Grow Your Social Media', currency_symbol: '₹', min_deposit: 10, max_deposit: 100000, maintenance_mode: '0' },
  newServiceForm: {
    category_id: 1,
    name: '',
    type: 'Default',
    price_per_k: 50,
    min_quantity: 100,
    max_quantity: 1000000,
    description: '',
    speed: 'Instant Start',
    refill: true,
    cancel: true
  },

  // Notifications State
  notifications: [],
  unreadNotificationCount: 0,
  showNotificationsModal: false,

  // Notification Toast
  toast: { show: false, message: '', type: 'success' },

  // Initialization
  async init() {
    await this.fetchSettings();
    await this.checkAuth();
    await this.fetchCategories();
    await this.fetchServices();
    await this.fetchNotifications();

    // GSAP Floating Ambient Animation for 3D elements
    this.$nextTick(() => {
      this.initGsapAnimations();
    });
  },

  showToast(message, type = 'success') {
    this.toast = { show: true, message, type };
    setTimeout(() => {
      this.toast.show = false;
    }, 4000);
  },

  // Navigation Helper
  navigateTo(page) {
    this.previousPage = this.currentPage;
    this.currentPage = page;
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // Refresh data corresponding to page
    if (page === 'orders') this.fetchOrders();
    if (page === 'transactions' || page === 'wallet') this.fetchTransactions();
    if (page === 'tickets') this.fetchTickets();
    if (page === 'notifications') this.fetchNotifications();
    if (page === 'dashboard' || page === 'profile') this.checkAuth();
    if (page === 'admin') this.fetchAdminData();
    if (page === 'new-order' && !this.selectedService && this.services.length > 0) {
      this.selectService(this.services[0]);
    }

    // Trigger GSAP page entrance
    this.$nextTick(() => {
      gsap.fromTo('.page-container', 
        { opacity: 0, y: 15 }, 
        { opacity: 1, y: 0, duration: 0.35, ease: 'power2.out' }
      );
    });
  },

  // API Call Wrapper
  async api(endpoint, options = {}) {
    const defaultHeaders = {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    };
    try {
      const res = await fetch(`/api${endpoint}`, {
        ...options,
        headers: { ...defaultHeaders, ...(options.headers || {}) }
      });
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.error || 'Server request failed');
      }
      return data;
    } catch (err) {
      console.error(`API Error on ${endpoint}:`, err);
      throw err;
    }
  },

  // Settings
  async fetchSettings() {
    try {
      const res = await this.api('/settings');
      if (res.success && res.settings) {
        this.settings = { ...this.settings, ...res.settings };
      }
    } catch (e) {
      // Ignore
    }
  },

  // Auth Functions
  async checkAuth() {
    try {
      const res = await this.api('/auth/me');
      if (res.authenticated && res.user) {
        this.user = res.user;
        this.isAuthenticated = true;
        this.profileForm.name = this.user.name;
        this.profileForm.phone = this.user.phone || '';
        this.profileForm.email = this.user.email;
      } else {
        this.user = null;
        this.isAuthenticated = false;
      }
    } catch (e) {
      this.user = null;
      this.isAuthenticated = false;
    }
  },

  async handleLogin() {
    this.authError = '';
    this.authSuccess = '';
    try {
      const res = await this.api('/auth/login', {
        method: 'POST',
        body: JSON.stringify(this.loginForm)
      });
      if (res.success) {
        this.user = res.user;
        this.isAuthenticated = true;
        this.showToast('Welcome back, ' + this.user.name);
        this.navigateTo('dashboard');
      }
    } catch (err) {
      this.authError = err.message;
    }
  },

  async handleRegister() {
    this.authError = '';
    this.authSuccess = '';
    try {
      const res = await this.api('/auth/register', {
        method: 'POST',
        body: JSON.stringify(this.registerForm)
      });
      if (res.success) {
        this.user = res.user;
        this.isAuthenticated = true;
        this.showToast('Account created successfully! Welcome to SMM Panel.');
        this.navigateTo('dashboard');
      }
    } catch (err) {
      this.authError = err.message;
    }
  },

  async handleLogout() {
    try {
      await this.api('/auth/logout', { method: 'POST' });
    } catch (e) {}
    this.user = null;
    this.isAuthenticated = false;
    this.showToast('Logged out successfully');
    this.navigateTo('landing');
  },

  async updateProfile() {
    try {
      const res = await this.api('/auth/profile', {
        method: 'POST',
        body: JSON.stringify(this.profileForm)
      });
      if (res.success) {
        this.showToast('Profile information updated successfully');
        await this.checkAuth();
      }
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  async updatePassword() {
    try {
      const res = await this.api('/auth/password', {
        method: 'POST',
        body: JSON.stringify(this.passwordForm)
      });
      if (res.success) {
        this.showToast('Password changed successfully');
        this.passwordForm = { current_password: '', new_password: '' };
      }
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  // Categories & Services
  async fetchCategories() {
    try {
      const res = await this.api('/categories');
      if (res.success) {
        this.categories = res.categories;
      }
    } catch (e) {}
  },

  async fetchServices() {
    try {
      const res = await this.api('/services');
      if (res.success) {
        this.services = res.services;
        if (!this.selectedService && this.services.length > 0) {
          this.selectService(this.services[0]);
        }
      }
    } catch (e) {}
  },

  get filteredServices() {
    return this.services.filter(s => {
      const matchesCategory = !this.selectedCategorySlug || s.category_slug === this.selectedCategorySlug;
      const matchesSearch = !this.serviceSearchQuery || 
        s.name.toLowerCase().includes(this.serviceSearchQuery.toLowerCase()) || 
        (s.description && s.description.toLowerCase().includes(this.serviceSearchQuery.toLowerCase()));
      return matchesCategory && matchesSearch;
    });
  },

  selectService(service) {
    this.selectedService = service;
    this.orderForm.service_id = service.id;
    this.orderForm.quantity = Math.max(1000, service.min_quantity);
    this.calculateOrderCharge();
  },

  calculateOrderCharge() {
    if (!this.selectedService) return;
    const pricePerK = parseFloat(this.selectedService.price_per_k);
    const qty = parseInt(this.orderForm.quantity) || 0;
    this.orderForm.calculatedCharge = ((qty / 1000) * pricePerK).toFixed(2);
  },

  adjustQuantity(delta) {
    if (!this.selectedService) return;
    let qty = parseInt(this.orderForm.quantity) || 0;
    qty = Math.max(this.selectedService.min_quantity, Math.min(this.selectedService.max_quantity, qty + delta));
    this.orderForm.quantity = qty;
    this.calculateOrderCharge();
  },

  // Order Placement
  async placeOrder() {
    if (!this.isAuthenticated) {
      this.navigateTo('login');
      return;
    }
    this.orderError = '';
    this.orderSuccess = null;
    this.isPlacingOrder = true;

    try {
      const res = await this.api('/orders/create', {
        method: 'POST',
        body: JSON.stringify({
          service_id: this.orderForm.service_id,
          link: this.orderForm.link,
          quantity: this.orderForm.quantity
        })
      });

      if (res.success) {
        this.orderSuccess = res.order;
        if (this.user) {
          this.user.balance = res.new_balance;
        }
        this.showToast(`Order #${res.order.id} placed successfully!`);
        this.orderForm.link = '';
        this.orderStep = 3; // Confirmation step
        await this.fetchOrders();
      }
    } catch (err) {
      this.orderError = err.message;
      this.showToast(err.message, 'error');
    } finally {
      this.isPlacingOrder = false;
    }
  },

  // Orders Retrieval
  async fetchOrders() {
    try {
      const statusParam = this.orderStatusFilter === 'All' ? '' : `?status=${encodeURIComponent(this.orderStatusFilter)}`;
      const res = await this.api(`/orders${statusParam}`);
      if (res.success) {
        this.orders = res.orders;
      }
    } catch (e) {}
  },

  setOrderStatusFilter(status) {
    this.orderStatusFilter = status;
    this.fetchOrders();
  },

  // Wallet & Deposit
  async fetchTransactions() {
    try {
      let typeParam = '';
      if (this.transactionFilter === 'Add Funds') typeParam = '?type=deposit';
      else if (this.transactionFilter === 'Orders') typeParam = '?type=order';
      else if (this.transactionFilter === 'Refunds') typeParam = '?type=refund';

      const res = await this.api(`/wallet/transactions${typeParam}`);
      if (res.success) {
        this.transactions = res.transactions;
      }
    } catch (e) {}
  },

  setTransactionFilter(filter) {
    this.transactionFilter = filter;
    this.fetchTransactions();
  },

  async handleDeposit() {
    if (!this.isAuthenticated) {
      this.navigateTo('login');
      return;
    }
    this.depositError = '';
    this.depositSuccess = '';
    this.isDepositing = true;

    try {
      const res = await this.api('/wallet/deposit', {
        method: 'POST',
        body: JSON.stringify({
          amount: this.depositAmount,
          payment_method: this.selectedPaymentMethod
        })
      });

      if (res.success) {
        this.user.balance = res.new_balance;
        this.depositSuccess = res.message;
        this.showToast(`Successfully deposited ₹${this.depositAmount} via ${this.selectedPaymentMethod}!`);
        await this.fetchTransactions();
      }
    } catch (err) {
      this.depositError = err.message;
      this.showToast(err.message, 'error');
    } finally {
      this.isDepositing = false;
    }
  },

  // Tickets
  async fetchTickets() {
    try {
      const statusParam = this.ticketFilter === 'All' ? '' : `?status=${encodeURIComponent(this.ticketFilter)}`;
      const res = await this.api(`/tickets${statusParam}`);
      if (res.success) {
        this.tickets = res.tickets;
      }
    } catch (e) {}
  },

  setTicketFilter(filter) {
    this.ticketFilter = filter;
    this.fetchTickets();
  },

  async createTicket() {
    this.ticketError = '';
    try {
      const res = await this.api('/tickets/create', {
        method: 'POST',
        body: JSON.stringify(this.newTicketForm)
      });
      if (res.success) {
        this.showToast('Ticket created successfully!');
        this.showNewTicketModal = false;
        this.newTicketForm = { subject: '', order_id: '', priority: 'medium', message: '' };
        await this.fetchTickets();
      }
    } catch (err) {
      this.ticketError = err.message;
    }
  },

  async viewTicket(ticketId) {
    try {
      const res = await this.api(`/tickets/${ticketId}`);
      if (res.success) {
        this.activeTicket = {
          ...res.ticket,
          messages: res.messages
        };
      }
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  async sendTicketReply() {
    if (!this.ticketReplyMessage.trim() || !this.activeTicket) return;
    try {
      const res = await this.api(`/tickets/${this.activeTicket.id}/reply`, {
        method: 'POST',
        body: JSON.stringify({ message: this.ticketReplyMessage })
      });
      if (res.success) {
        this.ticketReplyMessage = '';
        await this.viewTicket(this.activeTicket.id);
      }
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  // Notifications Methods
  async fetchNotifications() {
    try {
      const res = await this.api('/notifications');
      if (res.success) {
        this.notifications = res.notifications;
        this.unreadNotificationCount = res.unread_count;
      }
    } catch (e) {}
  },

  async markNotificationRead(notif) {
    if (notif.is_read) return;
    try {
      await this.api('/notifications/read', { method: 'POST', body: JSON.stringify({ id: notif.id }) });
      notif.is_read = 1;
      this.unreadNotificationCount = Math.max(0, this.unreadNotificationCount - 1);
    } catch (e) {}
  },

  async markAllNotificationsRead() {
    try {
      await this.api('/notifications/read-all', { method: 'POST' });
      this.notifications.forEach(n => n.is_read = 1);
      this.unreadNotificationCount = 0;
      this.showToast('All notifications marked as read');
    } catch (e) {}
  },

  // Admin Data & Operations
  async fetchAdminData() {
    try {
      const [statsRes, usersRes, ordersRes, txRes] = await Promise.all([
        this.api('/admin/stats'),
        this.api('/admin/users'),
        this.api('/admin/orders'),
        this.api('/admin/transactions')
      ]);
      if (statsRes.success) this.adminStats = statsRes.stats;
      if (usersRes.success) this.adminUsers = usersRes.users;
      if (ordersRes.success) this.adminOrders = ordersRes.orders;
      if (txRes.success) this.adminTransactions = txRes.transactions;
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  async updateAdminProfile() {
    try {
      const res = await this.api('/admin/profile', {
        method: 'POST',
        body: JSON.stringify(this.adminProfileForm)
      });
      if (res.success) {
        this.showToast('Admin profile updated successfully');
        this.adminProfileForm.password = '';
        await this.checkAuth();
      }
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  async updateAdminSettings() {
    try {
      const res = await this.api('/admin/settings', {
        method: 'POST',
        body: JSON.stringify(this.adminSettingsForm)
      });
      if (res.success) {
        this.showToast('System settings updated in MariaDB');
        await this.fetchSettings();
      }
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  async updateAdminOrderStatus(orderId, newStatus) {
    try {
      const res = await this.api('/admin/orders/status', {
        method: 'POST',
        body: JSON.stringify({ order_id: orderId, status: newStatus })
      });
      if (res.success) {
        this.showToast(`Order #${orderId} set to ${newStatus}`);
        await this.fetchAdminData();
      }
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  async toggleAdminUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'suspended' : 'active';
    try {
      const res = await this.api('/admin/users/status', {
        method: 'POST',
        body: JSON.stringify({ user_id: userId, status: newStatus })
      });
      if (res.success) {
        this.showToast(`User status updated to ${newStatus}`);
        await this.fetchAdminData();
      }
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  openBalanceModal(user) {
    this.adminBalanceModalUser = user;
    this.adminBalanceAmount = 100;
    this.adminBalanceAction = 'add';
  },

  async submitBalanceAdjustment() {
    if (!this.adminBalanceModalUser) return;
    try {
      const res = await this.api('/admin/users/balance', {
        method: 'POST',
        body: JSON.stringify({
          user_id: this.adminBalanceModalUser.id,
          amount: this.adminBalanceAmount,
          action: this.adminBalanceAction
        })
      });
      if (res.success) {
        this.showToast(res.message);
        this.adminBalanceModalUser = null;
        await this.fetchAdminData();
        await this.checkAuth();
      }
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  async createAdminService() {
    try {
      const res = await this.api('/admin/services', {
        method: 'POST',
        body: JSON.stringify(this.newServiceForm)
      });
      if (res.success) {
        this.showToast('New service published successfully!');
        await this.fetchServices();
      }
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  async toggleServiceStatus(serviceId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    try {
      const res = await this.api('/admin/services/toggle', {
        method: 'POST',
        body: JSON.stringify({ service_id: serviceId, status: newStatus })
      });
      if (res.success) {
        this.showToast(`Service status updated`);
        await this.fetchServices();
      }
    } catch (err) {
      this.showToast(err.message, 'error');
    }
  },

  // 3D Carousel Controls & GSAP Animations
  prevCarouselCard() {
    if (this.carouselIndex > 0) {
      this.carouselIndex--;
    } else {
      this.carouselIndex = this.carouselCards.length - 1;
    }
    this.animateCarousel();
  },

  nextCarouselCard() {
    if (this.carouselIndex < this.carouselCards.length - 1) {
      this.carouselIndex++;
    } else {
      this.carouselIndex = 0;
    }
    this.animateCarousel();
  },

  setCarouselCard(idx) {
    this.carouselIndex = idx;
    this.animateCarousel();
  },

  animateCarousel() {
    const cards = document.querySelectorAll('.carousel-card-item');
    if (!cards || cards.length === 0) return;

    cards.forEach((card, idx) => {
      const offset = idx - this.carouselIndex;
      
      // Calculate 3D transformation values
      let x = offset * 260; // horizontal spacing
      let z = -Math.abs(offset) * 140; // perspective depth pushback
      let rotateY = offset * -18; // 3D rotation toward center
      let scale = 1 - Math.abs(offset) * 0.14; // scale down side cards
      let opacity = Math.abs(offset) > 2 ? 0 : (1 - Math.abs(offset) * 0.28);
      let zIndex = 10 - Math.abs(offset);

      if (window.innerWidth < 640) {
        x = offset * 180;
        z = -Math.abs(offset) * 90;
        rotateY = offset * -14;
        scale = 1 - Math.abs(offset) * 0.18;
      }

      gsap.to(card, {
        x: x,
        z: z,
        rotationY: rotateY,
        scale: scale,
        opacity: opacity,
        zIndex: zIndex,
        duration: 0.55,
        ease: 'power3.out'
      });
    });
  },

  // Touch Swipe & Mouse Drag Handling for 3D Carousel
  onCarouselTouchStart(e) {
    this.isDraggingCarousel = true;
    this.carouselStartX = e.touches ? e.touches[0].clientX : e.clientX;
  },

  onCarouselTouchMove(e) {
    if (!this.isDraggingCarousel) return;
    this.carouselCurrentX = e.touches ? e.touches[0].clientX : e.clientX;
  },

  onCarouselTouchEnd(e) {
    if (!this.isDraggingCarousel) return;
    this.isDraggingCarousel = false;
    const diff = (this.carouselCurrentX || this.carouselStartX) - this.carouselStartX;
    if (diff > 45) {
      this.prevCarouselCard();
    } else if (diff < -45) {
      this.nextCarouselCard();
    }
    this.carouselStartX = 0;
    this.carouselCurrentX = 0;
  },

  initGsapAnimations() {
    this.animateCarousel();

    // Floating badges animation on landing page hero
    const floatingBadges = document.querySelectorAll('.hero-floating-icon');
    if (floatingBadges.length > 0) {
      gsap.to(floatingBadges, {
        y: 'random(-10, 10)',
        rotation: 'random(-5, 5)',
        duration: 2.8,
        repeat: -1,
        yoyo: true,
        ease: 'sine.inOut',
        stagger: 0.3
      });
    }
  }
}));

// Start Alpine
Alpine.start();
