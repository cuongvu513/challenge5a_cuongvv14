# Sử dụng PHP 8.2 với Apache
FROM php:8.2-apache

# Cài đặt các dependencies cần thiết
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Cài đặt PHP extensions
RUN docker-php-ext-install mysqli mbstring exif pcntl bcmath gd

# Bật Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy toàn bộ source code vào container
COPY . /var/www/html/

# Tạo thư mục uploads và phân quyền
RUN mkdir -p uploads/avatars uploads/assignments uploads/challenges uploads/submissions && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 777 uploads

# Cấu hình Apache để cho phép .htaccess
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/app.conf && \
    a2enconf app

# Mở port 80
EXPOSE 80

# Khởi động Apache
CMD ["apache2-foreground"]
