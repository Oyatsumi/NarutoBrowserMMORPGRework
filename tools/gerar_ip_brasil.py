"""Gera game/dados/ip_br4.bin e ip_br6.bin: faixas de IP do Brasil (para escolher o idioma automático no primeiro acesso).
Fonte: pacote npm @ip-location-db/geo-whois-asn-country (CC0; dados de origem do NRO, CC BY 4.0 - atribuição: nro.net).
Uso: python3 gerar_ip_brasil.py pasta_com_os_csv pasta_game/dados
Formato: ipv4 = pares (início, fim) uint32 big-endian (8 bytes); ipv6 = pares de 16 bytes (32 bytes), ordenados e mesclados."""
import sys, csv, ipaddress, struct, os
src, dst = sys.argv[1], sys.argv[2]
def faixas(arq, conv):
    r = []
    for a, b, c in csv.reader(open(os.path.join(src, arq))):
        if c == 'BR': r.append((conv(a), conv(b)))
    r.sort(); m = []
    for a, b in r:
        if m and a <= m[-1][1] + 1: m[-1] = (m[-1][0], max(m[-1][1], b))
        else: m.append((a, b))
    return m
v4 = faixas('geo-whois-asn-country-ipv4-num.csv', int)
v6 = faixas('geo-whois-asn-country-ipv6.csv', lambda s: int(ipaddress.IPv6Address(s)))
open(os.path.join(dst, 'ip_br4.bin'), 'wb').write(b''.join(struct.pack('>II', a, b) for a, b in v4))
open(os.path.join(dst, 'ip_br6.bin'), 'wb').write(b''.join(a.to_bytes(16, 'big') + b.to_bytes(16, 'big') for a, b in v6))
print(len(v4), 'faixas IPv4,', len(v6), 'faixas IPv6')
