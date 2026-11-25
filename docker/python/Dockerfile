FROM python:3.12-slim

RUN apt-get update && apt-get install -y \
    gcc \
    pkg-config \
    default-libmysqlclient-dev \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY tcp_server/requirements.txt .

RUN pip install --no-cache-dir -r requirements.txt

COPY tcp_server/ .

EXPOSE 9000

EXPOSE 8080

CMD ["python", "server.py"]