import paramiko

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect('45.132.157.58', port=65002, username='u853242961', password='Lucastav8012@', look_for_keys=False, allow_agent=False, timeout=20)

PATHS = [
    '/home/u853242961/domains/palevioletred-crow-490097.hostingersite.com/nodejs',
    '/home/u853242961/domains/transloggi.site/public_html',
]

FILES = [
    'webhook_pix.php',
    'gerar_taxa_pix.php',
    'admin_settings.php',
    'admin.php',
]

sftp = c.open_sftp()
for remote_dir in PATHS:
    for f in FILES:
        try:
            sftp.put(f, f'{remote_dir}/{f}')
            print(f'OK [{remote_dir.split("/")[-2]}]: {f}')
        except Exception as e:
            print(f'ERRO [{remote_dir}]: {f} -> {e}')
sftp.close()
print('Deploy concluido!')

c.close()
