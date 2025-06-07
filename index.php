<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mosoriot Uniforms Depot - Premium School Uniforms</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Include AOS animation library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'navy': '#001F3F',
                        'blue': '#0074D9',
                        'orange': '#FF851B',
                        'accent': '#2ECC40',
                        'dark': '#111827',
                        'light': '#F9FAFB'
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif'],
                        'opensans': ['Open Sans', 'sans-serif']
                    },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 6s ease-in-out infinite',
                        'fade-in': 'fadeIn 1s ease-in-out',
                        'bounce-slow': 'bounce 3s infinite'
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-15px)' }
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            scroll-behavior: smooth;
        }
        .hero-gradient {
            background: linear-gradient(135deg, rgba(0, 31, 63, 0.9) 0%, rgba(0, 116, 217, 0.8) 100%);
        }
        .hero-background {
            background-image: url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-1.2.1&auto=format&fit=crop&w=1600&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .btn-primary {
            background-color: #FF851B;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #E76F00;
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(255, 133, 27, 0.4);
        }
        .btn-secondary {
            background-color: #0074D9;
            transition: all 0.3s ease;
        }
        .btn-secondary:hover {
            background-color: #005EA6;
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 116, 217, 0.4);
        }
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px -12px rgba(0, 31, 63, 0.25);
        }
        .testimonial-card {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }
        .nav-link {
            position: relative;
        }
        .nav-link:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background-color: #FF851B;
            transition: width 0.3s ease;
        }
        .nav-link:hover:after {
            width: 100%;
        }
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        .order-highlight {
            background: linear-gradient(135deg, rgba(255, 133, 27, 0.1) 0%, rgba(255, 133, 27, 0.05) 100%);
            border-left: 4px solid #FF851B;
        }
        .infographic-item {
            position: relative;
        }
        .infographic-item:before {
            content: '';
            position: absolute;
            width: 40px;
            height: 40px;
            background-color: #0074D9;
            border-radius: 50%;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .infographic-item:nth-child(1):before {
            content: '1';
        }
        .infographic-item:nth-child(2):before {
            content: '2';
        }
        .infographic-item:nth-child(3):before {
            content: '3';
        }
        .confetti-btn:hover {
            animation: pulse-slow 2s infinite;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-navy shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-6 py-3">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center">
                    <div class="bg-blue p-2 rounded-lg">
                        <i class="fas fa-tshirt text-white text-2xl"></i>
                    </div>
                    <a href="#" class="ml-3 text-xl font-bold text-white font-poppins">Mosoriot Uniforms</a>
                </div>
                
                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button type="button" class="text-gray-300 hover:text-white focus:outline-none" id="menu-toggle">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                </div>
                
                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-4">
                <div class="flex items-center space-x-2 mr-4">
                    <a href="http://localhost/uniform_stock_management/authentication/login.php" class="btn-auth text-white px-4 py-2 rounded-lg font-medium hover:bg-white hover:bg-opacity-10">
                        <i class="fas fa-sign-in-alt mr-2"></i> Login
                    </a>
                </div>

                        </a>
                        <a href="http://localhost/uniform_stock_management/authentication/register.php" class="btn-auth text-white px-4 py-2 rounded-lg font-medium bg-blue hover:bg-blue-700">
                            <i class="fas fa-user-plus mr-2"></i> Register
                        </a>
                    </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#home" class="nav-link text-white hover:text-orange">Home</a>
                    <a href="#products" class="nav-link text-white hover:text-orange">Our Uniforms</a>
                    <a href="#process" class="nav-link text-white hover:text-orange">Order Process</a>
                    <a href="#testimonials" class="nav-link text-white hover:text-orange">Reviews</a>
                    <a href="#contact" class="nav-link text-white hover:text-orange">Contact</a>
                    <a href="uniforms.php" class="btn-primary text-white px-6 py-2 rounded-lg font-medium hover:shadow-lg transition-all duration-300 confetti-btn">
                        <i class="fas fa-shopping-cart mr-2"></i> Order Now
                    </a>
                </div>
            </div>
            
            <!-- Mobile Menu -->
            <div class="hidden md:hidden mt-4 pb-4" id="mobile-menu">
                <a href="#home" class="block py-3 text-white hover:text-orange border-b border-gray-700">Home</a>
                <a href="#products" class="block py-3 text-white hover:text-orange border-b border-gray-700">Our Uniforms</a>
                <a href="#process" class="block py-3 text-white hover:text-orange border-b border-gray-700">Order Process</a>
                <a href="#testimonials" class="block py-3 text-white hover:text-orange border-b border-gray-700">Reviews</a>
                <a href="#contact" class="block py-3 text-white hover:text-orange">Contact</a>
                <a href="uniforms.php" class="block mt-4 btn-primary text-white px-4 py-3 rounded-lg text-center font-medium confetti-btn">
                    <i class="fas fa-shopping-cart mr-2"></i> Order Now
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section with Background Image -->
    <section id="home" class="relative text-white py-20 md:py-32 overflow-hidden hero-background">
        <div class="absolute inset-0 z-0 hero-gradient"></div>
        <div class="container mx-auto px-6 flex flex-col md:flex-row items-center relative z-10">
            <div class="md:w-1/2 mb-12 md:mb-0" data-aos="fade-right">
                <h1 class="text-4xl md:text-5xl font-bold leading-tight mb-6 font-poppins">Premium School Uniforms for Confident Students</h1>
                <p class="text-xl mb-8 opacity-90">Designed for comfort, durability and school pride. Serving distinguished families with quality uniforms since 2010.</p>
                <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                    <a href="order_uniforms.php" class="btn-primary text-white font-bold py-4 px-8 rounded-lg text-center hover:shadow-lg transition-all duration-300 confetti-btn">
                        <i class="fas fa-shopping-cart mr-2"></i> Order Uniforms Now
                    </a>
                    <a href="uniforms.php" class="btn-secondary text-white font-bold py-4 px-8 rounded-lg text-center hover:shadow-lg transition-all duration-300">
                        <i class="fas fa-eye mr-2"></i> Browse Collection
                    </a>
                </div>
                
                <div class="mt-10 flex items-center space-x-6">
                    <div class="flex items-center">
                        <div class="flex -space-x-2">
                            <img src="https://randomuser.me/api/portraits/women/12.jpg" class="w-10 h-10 rounded-full border-2 border-white" alt="Happy customer">
                            <img src="https://randomuser.me/api/portraits/men/24.jpg" class="w-10 h-10 rounded-full border-2 border-white" alt="Happy customer">
                            <img src="https://randomuser.me/api/portraits/women/33.jpg" class="w-10 h-10 rounded-full border-2 border-white" alt="Happy customer">
                        </div>
                        <div class="ml-4">
                            <div class="flex items-center">
                                <i class="fas fa-star text-yellow-400 mr-1"></i>
                                <span class="font-medium">4.9/5</span>
                            </div>
                            <p class="text-sm opacity-80">Trusted by 1000+ parents</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="md:w-1/2 flex justify-center" data-aos="fade-left">
                <div class="relative">
                    <img src="https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80" 
                         alt="Happy students in Mosoriot Uniforms"
                         class="rounded-xl shadow-2xl floating max-w-md w-full border-4 border-white">
                    <div class="absolute -bottom-6 -right-6 bg-white p-4 rounded-xl shadow-lg hidden md:block">
                        <div class="flex items-center">
                            <div class="bg-blue text-white p-3 rounded-full mr-3">
                                <i class="fas fa-truck text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-dark">Fast Delivery</h4>
                                <p class="text-gray-600 text-sm">Within 2-3 business days</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section with Animated Infographics -->
    <section class="bg-white py-16">
        <div class="container mx-auto px-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div class="p-6 bg-gray-50 rounded-xl" data-aos="zoom-in">
                    <div class="text-4xl font-bold text-blue mb-2 font-poppins flex justify-center items-center">
                        <span class="counter" data-target="12">0</span>+
                    </div>
                    <div class="text-gray-600">Years Serving Families</div>
                    <div class="mt-4 h-1 bg-gradient-to-r from-blue to-navy rounded-full"></div>
                </div>
                <div class="p-6 bg-gray-50 rounded-xl" data-aos="zoom-in" data-aos-delay="100">
                    <div class="text-4xl font-bold text-blue mb-2 font-poppins flex justify-center items-center">
                        <span class="counter" data-target="60">0</span>+
                    </div>
                    <div class="text-gray-600">Partner Schools</div>
                    <div class="mt-4 h-1 bg-gradient-to-r from-blue to-navy rounded-full"></div>
                </div>
                <div class="p-6 bg-gray-50 rounded-xl" data-aos="zoom-in" data-aos-delay="200">
                    <div class="text-4xl font-bold text-blue mb-2 font-poppins flex justify-center items-center">
                        <span class="counter" data-target="10000">0</span>+
                    </div>
                    <div class="text-gray-600">Happy Students</div>
                    <div class="mt-4 h-1 bg-gradient-to-r from-blue to-navy rounded-full"></div>
                </div>
                <div class="p-6 bg-gray-50 rounded-xl" data-aos="zoom-in" data-aos-delay="300">
                    <div class="text-4xl font-bold text-blue mb-2 font-poppins">100%</div>
                    <div class="text-gray-600">Satisfaction Guarantee</div>
                    <div class="mt-4 h-1 bg-gradient-to-r from-blue to-navy rounded-full"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Order Highlight Section -->
    <section class="bg-white py-8 order-highlight" data-aos="fade-up">
        <div class="container mx-auto px-6">
            <div class="flex flex-col md:flex-row items-center justify-between p-6">
                <div class="mb-4 md:mb-0">
                    <h3 class="text-xl md:text-2xl font-bold text-dark mb-2 font-poppins">Ready to Order Your School Uniforms?</h3>
                    <p class="text-gray-700">Get premium quality uniforms delivered to your school or doorstep in just a few clicks!</p>
                </div>
                <a href="order_uniforms.php" class="btn-primary text-white font-bold py-3 px-8 rounded-lg hover:shadow-lg transition-all duration-300 whitespace-nowrap confetti-btn">
                    <i class="fas fa-shopping-cart mr-2"></i> Start Ordering Now
                </a>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section id="products" class="py-20 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-bold text-dark mb-4 font-poppins">Our Premium Uniform Collection</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Designed for comfort, durability and school pride. All uniforms come with official school logos and colors.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Product 1 -->
                <div class="product-card bg-white rounded-xl overflow-hidden shadow-md transition-all duration-300 hover:shadow-xl" data-aos="fade-up">
                    <div class="relative h-64 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1598033129183-c4f50c736f10?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80" 
                             alt="Boys Uniform" 
                             class="w-full h-full object-cover">
                        <div class="absolute top-4 right-4 bg-blue text-white text-xs font-bold px-3 py-1 rounded-full">
                            BEST SELLER
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-dark mb-2 font-poppins">Boys' Complete Uniform Set</h3>
                        <p class="text-gray-600 mb-4">Includes shirt, shorts, socks and tie. Available in all school colors.</p>
                        <div class="flex justify-between items-center">
                            <span class="text-blue font-bold text-xl">KSh 1,850</span>
                            <a href="order_uniforms.php?product=boys-set" class="btn-primary text-white px-4 py-2 rounded-lg hover:shadow-md transition confetti-btn">
                                Order Now
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Product 2 -->
                <div class="product-card bg-white rounded-xl overflow-hidden shadow-md transition-all duration-300 hover:shadow-xl" data-aos="fade-up" data-aos-delay="100">
                    <div class="relative h-64 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1551232864-3f0890e580d9?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80" 
                             alt="Girls Uniform" 
                             class="w-full h-full object-cover">
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-dark mb-2 font-poppins">Girls' Dress & Pinafore Set</h3>
                        <p class="text-gray-600 mb-4">Complete set with dress, pinafore, socks and hair ribbons.</p>
                        <div class="flex justify-between items-center">
                            <span class="text-blue font-bold text-xl">KSh 2,100</span>
                            <a href="order_uniforms.php?product=girls-set" class="btn-primary text-white px-4 py-2 rounded-lg hover:shadow-md transition confetti-btn">
                                Order Now
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Product 3 -->
                <div class="product-card bg-white rounded-xl overflow-hidden shadow-md transition-all duration-300 hover:shadow-xl" data-aos="fade-up" data-aos-delay="200">
                    <div class="relative h-64 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1529374255404-311a2a4f1fd9?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80" 
                             alt="Sports Uniform" 
                             class="w-full h-full object-cover">
                        <div class="absolute top-4 right-4 bg-accent text-white text-xs font-bold px-3 py-1 rounded-full">
                            NEW
                        </div>
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-dark mb-2 font-poppins">Sports Uniform Package</h3>
                        <p class="text-gray-600 mb-4">Breathable fabric for optimal performance. T-shirt, shorts and socks.</p>
                        <div class="flex justify-between items-center">
                            <span class="text-blue font-bold text-xl">KSh 2,250</span>
                            <a href="order_uniforms.php?product=sports-set" class="btn-primary text-white px-4 py-2 rounded-lg hover:shadow-md transition confetti-btn">
                                Order Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-12" data-aos="fade-up">
                <a href="order_uniforms.php" class="inline-block btn-secondary text-white font-bold py-3 px-8 rounded-lg hover:shadow-lg transition">
                    View All Uniform Options <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Order Process Infographic -->
    <section id="process" class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-bold text-dark mb-4 font-poppins">Simple 3-Step Order Process</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Getting your child's perfect uniform has never been easier</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Step 1 -->
                <div class="infographic-item bg-gray-50 p-8 rounded-xl text-center" data-aos="fade-up">
                    <div class="bg-blue text-white w-16 h-16 rounded-full flex items-center justify-center mb-6 mx-auto text-2xl font-bold">
                        1
                    </div>
                    <h3 class="text-xl font-bold text-dark mb-3 font-poppins">Select Your Items</h3>
                    <p class="text-gray-600">Browse our collection and add items to your cart. Use our size guide for perfect fit.</p>
                    <div class="mt-6 flex justify-center">
                        <div class="bg-blue bg-opacity-10 p-4 rounded-full">
                            <i class="fas fa-mouse-pointer text-blue text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2 -->
                <div class="infographic-item bg-gray-50 p-8 rounded-xl text-center" data-aos="fade-up" data-aos-delay="100">
                    <div class="bg-blue text-white w-16 h-16 rounded-full flex items-center justify-center mb-6 mx-auto text-2xl font-bold">
                        2
                    </div>
                    <h3 class="text-xl font-bold text-dark mb-3 font-poppins">Checkout Securely</h3>
                    <p class="text-gray-600">Enter delivery details and make payment via M-Pesa or credit card.</p>
                    <div class="mt-6 flex justify-center">
                        <div class="bg-blue bg-opacity-10 p-4 rounded-full">
                            <i class="fas fa-lock text-blue text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3 -->
                <div class="infographic-item bg-gray-50 p-8 rounded-xl text-center" data-aos="fade-up" data-aos-delay="200">
                    <div class="bg-blue text-white w-16 h-16 rounded-full flex items-center justify-center mb-6 mx-auto text-2xl font-bold">
                        3
                    </div>
                    <h3 class="text-xl font-bold text-dark mb-3 font-poppins">Receive Your Order</h3>
                    <p class="text-gray-600">Get delivery within 2-3 business days or pick up at our store.</p>
                    <div class="mt-6 flex justify-center">
                        <div class="bg-blue bg-opacity-10 p-4 rounded-full">
                            <i class="fas fa-truck text-blue text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-20 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-bold text-dark mb-4 font-poppins">What Parents Are Saying</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Don't just take our word for it - hear from parents who've experienced the Mosoriot Uniforms difference.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Testimonial 1 -->
                <div class="testimonial-card bg-white p-8 rounded-xl border border-gray-100" data-aos="fade-up">
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 mr-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-6 italic">"I was amazed at how well my son's uniform held up through the entire school year. The quality is truly superior to what we've bought elsewhere."</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full overflow-hidden mr-4 border-2 border-blue">
                            <img src="https://randomuser.me/api/portraits/women/43.jpg" alt="Sarah K." class="w-full h-full object-cover">
                        </div>
                        <div>
                            <h4 class="font-bold text-dark">Sarah K.</h4>
                            <p class="text-gray-500 text-sm">Parent at Mosoriot Primary</p>
                        </div>
                    </div>
                </div>
                
                <!-- Testimonial 2 -->
                <div class="testimonial-card bg-white p-8 rounded-xl border border-gray-100" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 mr-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-6 italic">"The ordering process was so simple, and the uniforms arrived faster than expected. My daughter loves how comfortable her new dress is!"</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full overflow-hidden mr-4 border-2 border-blue">
                            <img src="https://randomuser.me/api/portraits/men/33.jpg" alt="James M." class="w-full h-full object-cover">
                        </div>
                        <div>
                            <h4 class="font-bold text-dark">James M.</h4>
                            <p class="text-gray-500 text-sm">Parent at ACK Mosoriot</p>
                        </div>
                    </div>
                </div>
                
                <!-- Testimonial 3 -->
                <div class="testimonial-card bg-white p-8 rounded-xl border border-gray-100" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 mr-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-6 italic">"When we needed a replacement after my daughter grew, the exchange process was hassle-free. Great customer service!"</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full overflow-hidden mr-4 border-2 border-blue">
                            <img src="https://randomuser.me/api/portraits/women/65.jpg" alt="Grace W." class="w-full h-full object-cover">
                        </div>
                        <div>
                            <h4 class="font-bold text-dark">Grace W.</h4>
                            <p class="text-gray-500 text-sm">Parent at Mosoriot Academy</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Order CTA Section -->
    <section class="py-16 bg-navy text-white">
        <div class="container mx-auto px-6 text-center">
            <div class="max-w-3xl mx-auto" data-aos="zoom-in">
                <h2 class="text-3xl md:text-4xl font-bold mb-6 font-poppins">Ready to Order Your School Uniforms?</h2>
                <p class="text-xl mb-8 opacity-90">Join thousands of satisfied parents who trust Mosoriot Uniforms for quality school wear.</p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="order_uniforms.php" class="btn-primary text-white font-bold py-4 px-8 rounded-lg hover:shadow-lg transition confetti-btn">
                        <i class="fas fa-shopping-cart mr-2"></i> Order Now
                    </a>
                    <a href="tel:+254792264952" class="bg-white text-blue font-bold py-4 px-8 rounded-lg hover:shadow-lg transition">
                        <i class="fas fa-phone-alt mr-2"></i> Call Us: +254 792 264 952
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-20 bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-bold text-dark mb-4 font-poppins">We're Here to Help</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Have questions about sizing, delivery or anything else? Contact our friendly team.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <div data-aos="fade-right">
                    <form class="space-y-6">
                        <div>
                            <label for="name" class="block text-gray-700 mb-2 font-medium">Your Name</label>
                            <input type="text" id="name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue">
                        </div>
                        <div>
                            <label for="email" class="block text-gray-700 mb-2 font-medium">Email Address</label>
                            <input type="email" id="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue">
                        </div>
                        <div>
                            <label for="message" class="block text-gray-700 mb-2 font-medium">Your Message</label>
                            <textarea id="message" rows="5" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue"></textarea>
                        </div>
                        <button type="submit" class="btn-primary text-white font-bold py-3 px-8 rounded-lg hover:shadow-lg transition w-full">
                            Send Message
                        </button>
                    </form>
                </div>
                
                <div class="space-y-8" data-aos="fade-left">
                    <div class="flex items-start">
                        <div class="bg-blue bg-opacity-10 text-blue w-12 h-12 rounded-full flex items-center justify-center mr-6">
                            <i class="fas fa-map-marker-alt text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-dark mb-2 font-poppins">Visit Our Stores</h3>
                            <p class="text-gray-600">
                                <strong>Main Branch:</strong> Mosoriot Town Center, Shop 1 Opposite Hunters Hotel<br>
                                <strong>Second Branch:</strong> Opposite Kosirai Plaza, Along Eldoret-Kapsabet Road
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="bg-blue bg-opacity-10 text-blue w-12 h-12 rounded-full flex items-center justify-center mr-6">
                            <i class="fas fa-phone-alt text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-dark mb-2 font-poppins">Call Us</h3>
                            <p class="text-gray-600">
                                <strong>Phone:</strong> +254 792 264 952<br>
                                <strong>Hours:</strong> Monday-Saturday, 8:00 AM - 5:00 PM
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="bg-blue bg-opacity-10 text-blue w-12 h-12 rounded-full flex items-center justify-center mr-6">
                            <i class="fas fa-envelope text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-dark mb-2 font-poppins">Email Us</h3>
                            <p class="text-gray-600">
                                <strong>General Inquiries:</strong> info@mosoriotuniforms.co.ke<br>
                                <strong>Order Support:</strong> orders@mosoriotuniforms.co.ke
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-12">
        <div class="container mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        <div class="bg-blue p-2 rounded-lg mr-3">
                            <i class="fas fa-tshirt text-white text-xl"></i>
                        </div>
                        <span class="text-xl font-bold font-poppins">Mosoriot Uniforms</span>
                    </div>
                    <p class="text-gray-400 mb-4">Providing quality school uniforms to Mosoriot families since 2010.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white transition">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white transition">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold mb-4 font-poppins">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="#home" class="text-gray-400 hover:text-white transition">Home</a></li>
                        <li><a href="#products" class="text-gray-400 hover:text-white transition">Our Uniforms</a></li>
                        <li><a href="#process" class="text-gray-400 hover:text-white transition">Order Process</a></li>
                        <li><a href="#testimonials" class="text-gray-400 hover:text-white transition">Parent Reviews</a></li>
                        <li><a href="#contact" class="text-gray-400 hover:text-white transition">Contact Us</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold mb-4 font-poppins">Uniform Options</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Boys' Uniforms</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Girls' Uniforms</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Sports Wear</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Sweaters</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Accessories</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold mb-4 font-poppins">Stay Updated</h3>
                    <p class="text-gray-400 mb-4">Subscribe for new products, special offers and back-to-school reminders.</p>
                    <form class="flex">
                        <input type="email" placeholder="Your email" class="px-4 py-2 w-full rounded-l-lg focus:outline-none text-dark">
                        <button type="submit" class="bg-blue px-4 py-2 rounded-r-lg hover:bg-blue-700 transition">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-12 pt-8 text-center text-gray-400">
                <p>&copy; 2025 Mosoriot Uniforms Depot. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Include AOS animation library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- Include confetti library for celebration effect -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
    <script>
        // Initialize AOS animation library
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });

        // Mobile menu toggle
        document.getElementById('menu-toggle').addEventListener('click', function() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                    
                    // Close mobile menu if open
                    const mobileMenu = document.getElementById('mobile-menu');
                    if (!mobileMenu.classList.contains('hidden')) {
                        mobileMenu.classList.add('hidden');
                    }
                }
            });
        });

        // Counter animation for stats
        const counters = document.querySelectorAll('.counter');
        const speed = 200;

        counters.forEach(counter => {
            const animate = () => {
                const target = +counter.getAttribute('data-target');
                const count = +counter.innerText;
                const increment = target / speed;
                
                if (count < target) {
                    counter.innerText = Math.ceil(count + increment);
                    setTimeout(animate, 1);
                } else {
                    counter.innerText = target;
                }
            };
            
            // Start animation when element is in viewport
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    animate();
                }
            });
            
            observer.observe(counter);
        });

        // Confetti effect for order buttons
        document.querySelectorAll('.confetti-btn').forEach(button => {
            button.addEventListener('click', function() {
                confetti({
                    particleCount: 100,
                    spread: 70,
                    origin: { y: 0.6 },
                    colors: ['#FF851B', '#0074D9', '#2ECC40', '#FFFFFF']
                });
            });
        });
    </script>
</body>
</html>