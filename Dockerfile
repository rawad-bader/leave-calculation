# Use official PHP image
FROM php:8.2-cli

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Render provides PORT automatically
EXPOSE 10000

# Start PHP built-in server
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} index.php"]
