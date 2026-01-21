<?php

namespace CodelSoftware\LonomiaSdk\Support\PayloadReducer;

/**
 * Utilitários para truncar dados mantendo estrutura.
 *
 * Fornece métodos para reduzir tamanho de strings, arrays e estruturas
 * aninhadas preservando a estrutura JSON.
 */
class DataTruncator
{
    /**
     * Trunca uma string mantendo um indicador de truncamento.
     *
     * @param  string  $str  String a ser truncada
     * @param  int  $maxLength  Tamanho máximo
     * @return string String truncada
     */
    public function truncateString(string $str, int $maxLength): string
    {
        if (strlen($str) <= $maxLength) {
            return $str;
        }

        $truncated = substr($str, 0, $maxLength);
        $remaining = strlen($str) - $maxLength;

        return $truncated.'...[truncado '.$remaining.' caracteres]';
    }

    /**
     * Trunca um array limitando a quantidade de itens.
     *
     * @param  array  $arr  Array a ser truncado
     * @param  int  $maxItems  Quantidade máxima de itens
     * @return array Array truncado
     */
    public function truncateArray(array $arr, int $maxItems): array
    {
        if (count($arr) <= $maxItems) {
            return $arr;
        }

        $truncated = array_slice($arr, 0, $maxItems);
        $remaining = count($arr) - $maxItems;

        $truncated['...[truncado]'] = $remaining.' itens removidos';

        return $truncated;
    }

    /**
     * Trunca uma estrutura aninhada recursivamente.
     *
     * Mantém todas as chaves, reduz apenas valores:
     * - Strings são truncadas
     * - Arrays são limitados em quantidade
     * - Profundidade é limitada
     *
     * @param  mixed  $data  Dados a serem truncados
     * @param  int  $maxDepth  Profundidade máxima (0 = sem limite)
     * @param  int  $maxStringLength  Tamanho máximo de strings
     * @param  int  $maxArrayItems  Quantidade máxima de itens em arrays
     * @param  int  $currentDepth  Profundidade atual (usado internamente)
     * @return mixed Dados truncados
     */
    public function truncateNestedStructure(
        mixed $data,
        int $maxDepth = 5,
        int $maxStringLength = 500,
        int $maxArrayItems = 50,
        int $currentDepth = 0
    ): mixed {
        try {
            // Se excedeu profundidade, retorna placeholder
            if ($maxDepth > 0 && $currentDepth >= $maxDepth) {
                return '[profundidade máxima atingida]';
            }

            // Se for binário, retorna placeholder
            if ($this->isBinary($data)) {
                return '[dados binários]';
            }

            // Strings
            if (is_string($data)) {
                return $this->truncateString($data, $maxStringLength);
            }

            // Arrays
            if (is_array($data)) {
                $result = [];
                $itemCount = 0;

                foreach ($data as $key => $value) {
                    // Limita quantidade de itens em arrays
                    if ($maxArrayItems > 0 && $itemCount >= $maxArrayItems) {
                        $result['...[truncado]'] = (count($data) - $itemCount).' itens removidos';
                        break;
                    }

                    $result[$key] = $this->truncateNestedStructure(
                        $value,
                        $maxDepth,
                        $maxStringLength,
                        $maxArrayItems,
                        $currentDepth + 1
                    );
                    $itemCount++;
                }

                return $result;
            }

            // Objetos
            if (is_object($data)) {
                // Tenta converter para array
                try {
                    $array = json_decode(json_encode($data), true);
                    if (is_array($array)) {
                        return $this->truncateNestedStructure(
                            $array,
                            $maxDepth,
                            $maxStringLength,
                            $maxArrayItems,
                            $currentDepth + 1
                        );
                    }
                } catch (\Throwable $e) {
                    // Ignora erro de conversão
                }

                return '[objeto não serializável: '.get_class($data).']';
            }

            // Outros tipos (int, float, bool, null)
            return $data;
        } catch (\Throwable $e) {
            // Em caso de erro, retorna placeholder seguro
            return '[erro ao processar dados]';
        }
    }

    /**
     * Verifica se os dados são binários.
     *
     * @param  mixed  $data  Dados a serem verificados
     * @return bool True se for binário
     */
    public function isBinary(mixed $data): bool
    {
        if (! is_string($data)) {
            return false;
        }

        // Verifica se contém caracteres não imprimíveis (exceto quebras de linha e tabs)
        if (preg_match('/[\x00-\x08\x0B-\x0C\x0E-\x1F]/', $data)) {
            return true;
        }

        // Se a string for muito grande e não parecer texto, assume binário
        if (strlen($data) > 1000 && ! mb_check_encoding($data, 'UTF-8')) {
            return true;
        }

        return false;
    }
}
