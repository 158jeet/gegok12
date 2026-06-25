            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t py-4">
            <div class="container mx-auto px-4 text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> Gego K12. All rights reserved. |
                <a href="https://gegok12.com" target="_blank" class="text-purple-600 hover:underline">Documentation</a>
            </div>
        </footer>
    </div>

    <script>
        // Form validation helper
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return true;

            const inputs = form.querySelectorAll('input[required], select[required]');
            let valid = true;

            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('border-red-500');
                    valid = false;
                } else {
                    input.classList.remove('border-red-500');
                }
            });

            return valid;
        }
    </script>
</body>
</html>
