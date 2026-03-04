# Hướng dẫn Docker - Challenge 5a

## Giới thiệu
Dự án này đã được đóng gói với Docker để dễ dàng deploy. Ứng dụng sử dụng database online nên không cần cài đặt MySQL local.

## Yêu cầu
- Docker
- Docker Compose (tùy chọn)

## Cấu trúc files Docker
```
Dockerfile           - File cấu hình Docker image
docker-compose.yml   - File cấu hình Docker Compose (tùy chọn)
.dockerignore       - File loại trừ khi build image
```

## Cách 1: Chạy với Docker Compose (Đơn giản)

### 1. Build và chạy
```bash
docker-compose up -d
```

### 2. Truy cập ứng dụng
Mở trình duyệt và truy cập: http://localhost:8080

### 3. Dừng container
```bash
docker-compose down
```

## Cách 2: Chạy với Docker CLI

### 1. Build image
```bash
docker build -t challenge5a:latest .
```

### 2. Chạy container
```bash
docker run -d -p 8080:80 --name challenge5a_web challenge5a:latest
```

### 3. Truy cập ứng dụng
Mở trình duyệt và truy cập: http://localhost:8080

### 4. Dừng và xóa container
```bash
docker stop challenge5a_web
docker rm challenge5a_web
```

## Deploy lên Docker Hub

### 1. Đăng nhập Docker Hub
```bash
docker login
```
Nhập username và password của bạn.

### 2. Tag image với tên Docker Hub của bạn
```bash
docker tag challenge5a:latest <your-dockerhub-username>/challenge5a:latest
```
Thay `<your-dockerhub-username>` bằng username Docker Hub của bạn.

Ví dụ:
```bash
docker tag challenge5a:latest cuongvv14/challenge5a:latest
```

### 3. Push image lên Docker Hub
```bash
docker push <your-dockerhub-username>/challenge5a:latest
```

Ví dụ:
```bash
docker push cuongvv14/challenge5a:latest
```

### 4. Verify trên Docker Hub
Truy cập https://hub.docker.com/ và kiểm tra repository của bạn.

## Chạy từ Docker Hub

Sau khi đã push lên Docker Hub, người khác có thể chạy ứng dụng bằng:

```bash
docker run -d -p 8080:80 --name challenge5a <your-dockerhub-username>/challenge5a:latest
```

Hoặc với docker-compose, tạo file `docker-compose.yml`:
```yaml
version: '3.8'

services:
  web:
    image: <your-dockerhub-username>/challenge5a:latest
    container_name: challenge5a_web
    restart: unless-stopped
    ports:
      - "8080:80"
    volumes:
      - ./uploads:/var/www/html/uploads
```

Sau đó chạy:
```bash
docker-compose up -d
```

## Các lệnh Docker hữu ích

### Xem logs
```bash
docker logs challenge5a_web
docker logs -f challenge5a_web  # Theo dõi logs real-time
```

### Xem danh sách containers
```bash
docker ps           # Containers đang chạy
docker ps -a        # Tất cả containers
```

### Xem danh sách images
```bash
docker images
```

### Xóa image
```bash
docker rmi challenge5a:latest
```

### Truy cập vào container (debug)
```bash
docker exec -it challenge5a_web bash
```

### Copy files từ/vào container
```bash
# Copy từ host vào container
docker cp local-file.txt challenge5a_web:/var/www/html/

# Copy từ container ra host
docker cp challenge5a_web:/var/www/html/file.txt ./
```

## Cấu hình Database

Ứng dụng này sử dụng database online (sql12.freesqldatabase.com), thông tin kết nối đã được cấu hình trong file `config/db.php`. Không cần cài đặt MySQL local.

## Lưu ý

1. **Port**: Ứng dụng chạy trên port 8080. Nếu port này đã được sử dụng, thay đổi port trong docker-compose.yml hoặc khi chạy docker run:
   ```bash
   docker run -d -p 9090:80 --name challenge5a_web challenge5a:latest
   ```

2. **Uploads**: Thư mục uploads được mount từ host để dữ liệu tồn tại khi container restart.

3. **Database Connection**: Đảm bảo server có thể kết nối tới database online. Kiểm tra firewall nếu cần.

## Troubleshooting

### Container không start
```bash
# Xem logs để tìm lỗi
docker logs challenge5a_web
```

### Port đã được sử dụng
```bash
# Đổi port mapping
docker run -d -p 9090:80 --name challenge5a_web challenge5a:latest
```

### Xóa tất cả để build lại từ đầu
```bash
docker-compose down
docker rmi challenge5a:latest
docker-compose up -d --build
```

## Hỗ trợ

Nếu gặp vấn đề, hãy kiểm tra:
1. Docker đã được cài đặt và chạy
2. Port 8080 chưa được sử dụng
3. Kết nối internet để kết nối database online
4. Logs của container: `docker logs challenge5a_web`
