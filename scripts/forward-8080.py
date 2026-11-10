import socket
import threading

LISTEN = ("127.0.0.1", 8080)
TARGET = ("172.29.128.92", 8080)


def pipe(src, dst):
    try:
        while True:
            data = src.recv(65536)
            if not data:
                break
            dst.sendall(data)
    except OSError:
        pass
    finally:
        for sock in (src, dst):
            try:
                sock.close()
            except OSError:
                pass


def handle(client):
    try:
        remote = socket.create_connection(TARGET, 5)
    except OSError:
        client.close()
        return
    threading.Thread(target=pipe, args=(client, remote), daemon=True).start()
    pipe(remote, client)


def main():
    server = socket.socket()
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    server.bind(LISTEN)
    server.listen(50)
    print("forwarding 127.0.0.1:8080 -> 172.29.128.92:8080", flush=True)
    while True:
        client, _ = server.accept()
        threading.Thread(target=handle, args=(client,), daemon=True).start()


if __name__ == "__main__":
    main()
