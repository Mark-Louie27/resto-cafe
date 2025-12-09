    <!-- Footer -->
    <footer class="bg-gray-800 text-white pt-12 pb-6">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- About -->
                <div>
                    <h3 class="text-xl font-bold mb-4">About Casa Baraka</h3>
                    <p class="text-gray-400">Your cozy corner for delicious coffee and treats since 2010. We source our beans from sustainable farms and bake fresh daily.</p>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-xl font-bold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="/" class="text-gray-400 hover:text-amber-500 transition">Home</a></li>
                        <li><a href="/menu.php" class="text-gray-400 hover:text-amber-500 transition">Menu</a></li>
                        <li><a href="/about.php" class="text-gray-400 hover:text-amber-500 transition">About Us</a></li>
                        <li><a href="/contact.php" class="text-gray-400 hover:text-amber-500 transition">Contact</a></li>
                        <li><a href="/privacy.php" class="text-gray-400 hover:text-amber-500 transition">Privacy Policy</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div>
                    <h3 class="text-xl font-bold mb-4">Contact Us</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li class="flex items-center">
                            <i class="fas fa-map-marker-alt mr-2 text-amber-500"></i>
                            123 Coffee Street, Portland, OR 97205
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone mr-2 text-amber-500"></i>
                            (503) 555-1234
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope mr-2 text-amber-500"></i>
                            hello@casabaraka.com
                        </li>
                    </ul>
                </div>

                <!-- Newsletter -->
                <div>
                    <h3 class="text-xl font-bold mb-4">Newsletter</h3>
                    <p class="text-gray-400 mb-4">Subscribe to get updates on special offers and events.</p>
                    <form class="flex">
                        <input type="email" placeholder="Your email" class="px-4 py-2 w-full rounded-l focus:outline-none text-gray-800">
                        <button type="submit" class="bg-amber-600 hover:bg-amber-500 px-4 py-2 rounded-r">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                    <div class="flex space-x-4 mt-4">
                        <a href="#" class="text-gray-400 hover:text-amber-500 text-xl"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-gray-400 hover:text-amber-500 text-xl"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-gray-400 hover:text-amber-500 text-xl"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-gray-400 hover:text-amber-500 text-xl"><i class="fab fa-yelp"></i></a>
                    </div>
                </div>
            </div>

            <!-- Copyright -->
            <div class="border-t border-gray-700 mt-8 pt-6 text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> Casa Baraka. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button id="backToTop" class="fixed bottom-6 right-6 bg-amber-600 text-white p-3 rounded-full shadow-lg opacity-0 invisible transition-all duration-300">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
        // Back to top button
        const backToTopButton = document.getElementById('backToTop');

        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.remove('opacity-0', 'invisible');
                backToTopButton.classList.add('opacity-100', 'visible');
            } else {
                backToTopButton.classList.remove('opacity-100', 'visible');
                backToTopButton.classList.add('opacity-0', 'invisible');
            }
        });

        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>
    </body>

    </html>