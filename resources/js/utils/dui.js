export function formatearValorDui(valor) {
    const digitos = valor.replace(/[^0-9]/g, '').slice(0, 9);
    return digitos.length > 8 ? `${digitos.slice(0, 8)}-${digitos.slice(8)}` : digitos;
}
