# Paycan - Payment Integration Service

<!-- Banner placeholder - Add your project banner/logo here -->
<!-- ![Project Screebshot](https://i.imghippo.com/files/CFjt6957sKU.png) -->

<p align="center">
  <img src="https://i.imghippo.com/files/HJK6444eGY.jpeg" alt="Image 2" width="54%"/>
  <img src="https://i.imghippo.com/files/mNzB7064odo.jpeg" alt="Image 1" width="36%"/>
</p>

**⚠️ Development Notice**

 This application is currently under active development and is **not production-ready**. Please use this for learning, development, and testing purposes only. Want to contribute? We'd love your help! Check out our [contribution guidelines](#-contributing) below to get started.

## 🚀 Philosophy

**Payment integration shouldn't be complicated.**

We believe every developer should be able to collect money with just a few lines of code, without being locked into a single payment provider. Our mission is to create a unified, vendor-agnostic payment integration that works seamlessly with any payment gateway while maintaining simplicity and flexibility.

No webhook handling, no customer creation, no subscription management.

### Core Principles

- **Developer First**: Minimal setup, maximum functionality
- **No Vendor Lock-in**: Switch between payment providers without rewriting your entire payment system
- **Unified API**: One interface for all payment gateways
- **Production Ready**: Built with best practices and extensive testing

## ✨ Features

- 🔌 **Multiple Payment Gateways**: Currently support for PayPal and Stripe (more soon)
- 📊 **Subscription Management**: Handle recurring payments effortlessly
- 💳 **One-time Payments**: Simple integration for single transactions
- 🎯 **Plan Management**: Dynamic subscription plan handling
- 🔐 **Secure by Design**: Use security best practices
- 🧪 **Fully Tested**: Comprehensive test coverage with Pest
- 📖 **Well Documented**: Clear documentation and examples (soon)
- 🌐 **API First**: RESTful API for headless implementations 

<!-- ## 🚀 Quick Start

Get started in just a few lines of code:

```bash
composer require paycan-app/paycan
``` -->

## 🛠 Installation

1. Clone the repository
```bash
git clone https://github.com/paycan-app/paycan.git
cd paycan
```

2. Install dependencies
```bash
composer install
npm install
```

3. Set up your environment
```bash
cp .env.example .env
php artisan key:generate
```

4. Configure your database
```bash
php artisan migrate
```

5. Start developing
```bash
npm run dev
php artisan serve
```

6. Test User (with seed)
```bash
email: 'test@example.com',
password: 'password'
```

<!-- ## 📚 Documentation
Soon -->



## 🤝 Contributing

We welcome contributions from the community! Whether you're fixing bugs, adding new payment gateways, improving documentation, or suggesting new features, your help is appreciated.

### How to Contribute

1. **Fork the repository**
2. **Create your feature branch** (`git checkout -b feature/amazing-feature`)
3. **Write tests** for your changes
4. **Ensure tests pass** (`php artisan test`)
5. **Commit your changes** (`git commit -m 'Add amazing feature'`)
6. **Push to the branch** (`git push origin feature/amazing-feature`)
7. **Open a Pull Request**

### Areas We Need Help With

- 🎨 **Frontend/UI**: Improve payment forms and user experience
- 🔌 **Gateway Integrations**: Add support for new payment providers
- 📖 **Documentation**: Help for our guides and examples
- 🧪 **Testing**: Expand test coverage and edge cases
- 🌍 **Localization**: Translate to different languages
- 🔧 **DevOps**: CI/CD improvements and deployment automation

## 🎯 Roadmap
- [ ] **Admin Backend**: Yo manage products, orders, subscriptions, etc.
- [ ] **Advanced Analytics**: Payment insights and reporting
- [ ] **Additional Gateways**: Braintree, Razorpay, Mollie, Shetab
- [ ] **Marketplace Support**: Split payments and marketplace fees
- [ ] **Multi-currency**: Enhanced currency conversion support
- [ ] **Fraud Detection**: Built-in fraud prevention tools
- [ ] **SDK**: SDK to work with API

## 📄 License

This project is licensed under the AGPLv3 License 

<!-- ## 🙏 Acknowledgments

- Laravel community for the amazing framework
- Payment gateway providers for their APIs
- All contributors who make this project possible

## 📞 Support

- 📧 **Email**: support@your-domain.com
- 💬 **Discord**: [Join our community](https://discord.gg/your-invite)
- 🐛 **Issues**: [GitHub Issues](https://github.com/your-username/laravel-payment-integration/issues)
- 📖 **Docs**: [Documentation](https://docs.your-domain.com) -->

---

<div align="center">
  <strong>Made with ❤️ by the Paycan team</strong>
</div>