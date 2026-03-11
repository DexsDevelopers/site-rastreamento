const mysql = require('mysql2/promise');
const dotenv = require('dotenv');
const path = require('path');

dotenv.config({ path: path.join(__dirname, '.env') });

async function checkCodes() {
    let connection;
    try {
        connection = await mysql.createConnection({
            host: process.env.DB_HOST === 'localhost' ? '127.0.0.1' : process.env.DB_HOST,
            user: process.env.DB_USER,
            password: process.env.DB_PASSWORD,
            database: process.env.DB_NAME
        });

        console.log('--- Verificando códigos de rastreio ---');
        const [rows] = await connection.query('SELECT DISTINCT codigo FROM rastreios_status');

        const affected = rows.filter(r => r.codigo !== r.codigo.trim());

        if (affected.length === 0) {
            console.log('Nenhum código com espaços encontrado pelo método simples.');
            console.log('Lista de todos os códigos (entre aspas para ver espaços):');
            rows.forEach(r => console.log(`"${r.codigo}"`));
        } else {
            console.log(`Encontrados ${affected.length} códigos com espaços:`);
            affected.forEach(r => console.log(`"${r.codigo}"`));
        }

    } catch (err) {
        console.error('Erro:', err.message);
    } finally {
        if (connection) await connection.end();
    }
}

checkCodes();
