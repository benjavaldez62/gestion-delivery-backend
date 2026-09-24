<?php

namespace App\Swagger;

/**
 * @OA\Info(
 *     title="API del Sistema de Delivery",
 *     version="1.0.0",
 *     description="Documentación de la API para el Sistema de Delivery (Gestión de Pedidos, Pagos, Usuarios, etc.)"
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Servidor Local"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class OpenApi
{
}
